<?php

namespace App\Service;

use App\Entity\Member;
use App\Entity\Profile;
use App\Entity\Server;
use App\Entity\ServerImage;
use App\Entity\ServerSettings;
use App\Entity\User;
use App\Enum\MemberRole;
use App\Repository\MemberRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Security\ServerVoter;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ServerService
{
    private const MAX_SIZE = 10 * 1024 * 1024; // 10 MB
    private const MAX_DIMENSION = 8000;        // Schutz vor Decompression-Bombs
    private const ALLOWED = [
        'image/jpeg' => ['ext' => 'jpg', 'magic' => "\xFF\xD8\xFF"],
        'image/png'  => ['ext' => 'png', 'magic' => "\x89PNG\r\n\x1A\n"],
    ];

    private const MAX_SERVERS_PER_OWNER = 10;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServerRepository $servers,
        private MemberRepository $members,
        private UserRepository $users,
        private ValidatorInterface $validator,
        #[Autowire('%kernel.project_dir%/var/uploads')]
        private string $uploadDir,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function create(mixed $currentUser, array $data): OperationResult
    {
        $error = $this->validateSettingsInput($data, true);
        if ($error !== null) {
            return new OperationResult(['error' => $error], 400);
        }
        $owner = $this->requireUser($currentUser);

        $server = $this->entityManager->wrapInTransaction(function () use ($owner, $data): Server {
            $this->entityManager->lock($owner, LockMode::PESSIMISTIC_WRITE);
            if ($this->servers->countCreatedBy($owner) >= self::MAX_SERVERS_PER_OWNER) {
                throw new ConflictHttpException('A user can create at most 10 servers.');
            }

            $server = (new Server())->setCreatedBy($owner);
            $settings = (new ServerSettings())
                ->setName($data['name'])
                ->setDescription($data['description'] ?? null)
                ->setPublicy($data['publicy'] ?? false)
                ->setProfileImage($data['profile_image'] ?? null);
            $server->setSettings($settings);
            $server->addMember(new Member($server, $owner, MemberRole::Owner));
            $this->validate($settings);

            $this->entityManager->persist($server);
            $this->entityManager->flush();

            return $server;
        });

        return new OperationResult($this->serverData($server), 201);
    }

    public function publicServers(mixed $search): OperationResult
    {
        if ($search !== null && !is_string($search)) {
            return new OperationResult(['error' => 'q must be a string.'], 400);
        }
        $search = $search === null ? null : trim($search);
        if ($search === '') {
            return new OperationResult(['error' => 'q must not be empty.'], 400);
        }

        return new OperationResult(array_map(
            $this->searchServerData(...),
            $this->servers->findPublicServers($search),
        ));
    }

    public function myServers(mixed $currentUser): OperationResult
    {
        $user = $this->requireUser($currentUser);
        $servers = array_map(
            static fn (Member $member): Server => $member->getServer(),
            $this->members->findByUser($user),
        );

        return new OperationResult(array_map($this->serverData(...), $servers));
    }

    public function show(string $uuid): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::VIEW, $server);

        return new OperationResult($this->serverData($server));
    }

    public function updateSettings(string $uuid, array $data): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::MANAGE, $server);
        $error = $this->validateSettingsInput($data, false);
        if ($error !== null) {
            return new OperationResult(['error' => $error], 400);
        }

        $settings = $server->getSettings();
        if ($settings === null) {
            throw new \LogicException('Server settings are missing.');
        }
        if (array_key_exists('name', $data)) {
            $settings->setName($data['name']);
        }
        if (array_key_exists('description', $data)) {
            $settings->setDescription($data['description']);
        }
        if (array_key_exists('publicy', $data)) {
            $settings->setPublicy($data['publicy']);
        }
        if (array_key_exists('profile_image', $data)) {
            $settings->setProfileImage($data['profile_image']);
        }
        $this->validate($settings);
        $this->entityManager->flush();

        return new OperationResult($this->serverData($server));
    }

    public function delete(string $uuid): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::MANAGE, $server);
        $this->entityManager->remove($server);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    public function members(string $uuid): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::VIEW, $server);

        return new OperationResult(array_map(
            $this->memberData(...),
            $this->members->findByServer($server),
        ));
    }

    public function join(string $uuid, mixed $currentUser): OperationResult
    {
        $server = $this->findServer($uuid);
        if ($server->getSettings()?->isPublicy() !== true) {
            throw new AccessDeniedHttpException('Only public servers can be joined without an invitation.');
        }
        $user = $this->requireUser($currentUser);
        if ($this->members->findMembership($server, $user) !== null) {
            return new OperationResult(['error' => 'You are already a member of this server.'], 409);
        }

        $member = new Member($server, $user);
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return new OperationResult($this->memberData($member), 201);
    }

    public function invite(string $uuid, string $userUuid, mixed $currentUser, array $data): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::MANAGE_CHANNELS, $server);
        $roleValue = $data['role'] ?? MemberRole::Member->value;
        if (!is_string($roleValue) || !in_array($roleValue, [MemberRole::Member->value, MemberRole::Editor->value], true)) {
            return new OperationResult(['error' => 'Invited members can have the member or editor role.'], 400);
        }

        $inviter = $this->members->findMembership($server, $this->requireUser($currentUser));
        if ($inviter?->getRole() === MemberRole::Editor && $roleValue !== MemberRole::Member->value) {
            throw new AccessDeniedHttpException('Editors can invite members but cannot assign editor roles.');
        }
        $user = $this->users->findOneBy(['uuid' => $userUuid]);
        if (!$user instanceof User) {
            throw new NotFoundHttpException('User not found.');
        }
        if ($this->members->findMembership($server, $user) !== null) {
            return new OperationResult(['error' => 'This user is already a member of the server.'], 409);
        }

        $member = new Member($server, $user, MemberRole::from($roleValue));
        $this->entityManager->persist($member);
        $this->entityManager->flush();

        return new OperationResult($this->memberData($member), 201);
    }

    public function leave(string $uuid, mixed $currentUser): OperationResult
    {
        $server = $this->findServer($uuid);
        $member = $this->members->findMembership($server, $this->requireUser($currentUser));
        if ($member === null) {
            throw new NotFoundHttpException('Membership not found.');
        }
        if ($member->getRole() === MemberRole::Owner && $this->members->countOwners($server) <= 1) {
            return new OperationResult(['error' => 'Transfer ownership before leaving this server.'], 409);
        }

        $this->entityManager->remove($member);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    public function updateMember(string $uuid, string $userUuid, array $data): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::MANAGE, $server);
        $member = $this->findMember($server, $userUuid);
        $roleValue = $data['role'] ?? null;
        if (!is_string($roleValue) || ($role = MemberRole::tryFrom($roleValue)) === null) {
            return new OperationResult(['error' => 'role must be one of: member, editor, owner.'], 400);
        }
        if ($member->getRole() === MemberRole::Owner && $role !== MemberRole::Owner && $this->members->countOwners($server) <= 1) {
            return new OperationResult(['error' => 'A server must have at least one owner.'], 409);
        }

        $member->setRole($role);
        $this->entityManager->flush();

        return new OperationResult($this->memberData($member));
    }

    public function removeMember(string $uuid, string $userUuid): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::MANAGE, $server);
        $member = $this->findMember($server, $userUuid);
        if ($member->getRole() === MemberRole::Owner && $this->members->countOwners($server) <= 1) {
            return new OperationResult(['error' => 'A server must have at least one owner.'], 409);
        }

        $this->entityManager->remove($member);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    public function updateImage(string $uuid, ?UploadedFile $image): OperationResult
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::MANAGE, $server);

        $settings = $server->getSettings();
        if ($settings === null) {
            throw new \LogicException('Server settings are missing.');
        }

        if ($image === null) {
            return new OperationResult(['error' => 'No image file provided.'], 400);
        }

        // isValid() prüft Upload-Fehler und is_uploaded_file()
        if (!$image->isValid()) {
            return new OperationResult(['error' => 'Invalid image file.'], 400);
        }

        if ($image->getSize() === false || $image->getSize() > self::MAX_SIZE) {
            return new OperationResult(['error' => 'File too large (max 10 MB).'], 413);
        }

        $path = $image->getPathname();

        // 1. MIME anhand des Inhalts (nicht vom Client-Header)
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!isset(self::ALLOWED[$mime])) {
            return new OperationResult(['error' => 'Only PNG and JPEG allowed.'], 415);
        }

        // 2. Magic Bytes
        $handle = fopen($path, 'rb');
        $head = $handle ? fread($handle, 8) : '';
        if ($handle) {
            fclose($handle);
        }
        if (!str_starts_with($head, self::ALLOWED[$mime]['magic'])) {
            return new OperationResult(['error' => 'File signature mismatch.'], 415);
        }

        // 3. Ist es wirklich ein parsebares Bild + Größenlimit
        $info = @getimagesize($path);
        if ($info === false || $info['mime'] !== $mime
            || $info[0] > self::MAX_DIMENSION || $info[1] > self::MAX_DIMENSION) {
            return new OperationResult(['error' => 'Invalid or too large image.'], 400);
        }

        // 4. Sanitizing: neu encoden -> EXIF, eingebetteter Müll, Polyglots sind weg
        $img = $mime === 'image/jpeg' ? @imagecreatefromjpeg($path) : @imagecreatefrompng($path);
        if ($img === false) {
            return new OperationResult(['error' => 'Could not process image.'], 400);
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0750, true) && !is_dir($this->uploadDir)) {
            return new OperationResult(['error' => 'Storage unavailable.'], 500);
        }

        $old = $settings->getServerImage();
        $oldName = $old?->getName();

        $filename = bin2hex(random_bytes(16)) . '.' . self::ALLOWED[$mime]['ext']; // nie den Client-Namen nehmen
        $target = $this->uploadDir . '/' . $filename;

        if ($mime === 'image/jpeg') {
            $ok = imagejpeg($img, $target, 90);
        } else {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $ok = imagepng($img, $target, 6);
        }

        if (!$ok) {
            return new OperationResult(['error' => 'Could not save image.'], 500);
        }

        // Bestehende Entity wiederverwenden, nur bei Erstupload neu anlegen
        $serverImage = $old ?? new ServerImage();
        $serverImage->setName($filename);
        if ($serverImage->getUuid() === null) {
            $serverImage->setUuid(Uuid::v4());
        }
        if ($old === null) {
            $settings->setServerImage($serverImage);
            $this->entityManager->persist($serverImage);
        }

        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            @unlink($target); // neue Datei wieder wegräumen, alte bleibt unangetastet
            throw $e;
        }

        // Alte Datei erst nach erfolgreichem Flush löschen
        if ($oldName !== null) {
            @unlink($this->uploadDir . '/' . basename($oldName));
        }

        return new OperationResult($this->serverData($server));
    }

    public function getImagePath(string $uuid): ?string
    {
        $server = $this->findServer($uuid);
        $this->requireAuthorization(ServerVoter::VIEW, $server);

        $image = $server->getSettings()?->getServerImage();
        if ($image === null || $image->getName() === null) {
            return null;
        }

        $path = $this->uploadDir . '/' . basename($image->getName());

        return is_file($path) ? $path : null;
    }

    private function findServer(string $uuid): Server
    {
        $server = $this->servers->findOneBy(['uuid' => $uuid]);
        if (!$server instanceof Server) {
            throw new NotFoundHttpException('Server not found.');
        }

        return $server;
    }

    private function findMember(Server $server, string $userUuid): Member
    {
        $member = $this->members->findByServerAndUserUuid($server, $userUuid);
        if (!$member instanceof Member) {
            throw new NotFoundHttpException('Member not found.');
        }

        return $member;
    }

    private function requireUser(mixed $user): User
    {
        if (!$user instanceof User || !$this->authorization->isGranted('ROLE_USER')) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    private function requireAuthorization(string $attribute, Server $server): void
    {
        if (!$this->authorization->isGranted($attribute, $server)) {
            throw new AccessDeniedHttpException();
        }
    }

    private function validateSettingsInput(array $data, bool $creating): ?string
    {
        if ($creating && (!isset($data['name']) || !is_string($data['name']))) {
            return 'name is required and must be a string.';
        }
        foreach (['name', 'description', 'profile_image'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && !is_string($data[$field])) {
                return sprintf('%s must be a string or null.', $field);
            }
        }
        if (array_key_exists('publicy', $data) && !is_bool($data['publicy'])) {
            return 'publicy must be a boolean.';
        }

        return null;
    }

    private function validate(ServerSettings $settings): void
    {
        $errors = [];
        foreach ($this->validator->validate($settings) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }
        if ($errors !== []) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                json_encode($errors, JSON_THROW_ON_ERROR),
            );
        }
    }

    private function serverData(Server $server): array
    {
        $settings = $server->getSettings();

        return [
            'uuid' => $server->getUuid(),
            'member_count' => $server->getMemberCount(),
            'created_at' => $server->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'settings' => $settings === null ? null : [
                'name' => $settings->getName(),
                'description' => $settings->getDescription(),
                'publicy' => $settings->isPublicy(),
                'profile_image' => $settings->getProfileImage(),
            ],
        ];
    }

    private function searchServerData(Server $server): array
    {
        return [
            'uuid' => $server->getUuid(),
            'member_count' => $server->getMemberCount(),
            'created_at' => $server->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'name' => $server->getSettings()?->getName(),
            'profile_image' => $server->getSettings()?->getProfileImage(),
        ];
    }

    private function memberData(Member $member): array
    {
        $user = $member->getUser();
        $profile = $user?->getProfile();

        return [
            'user_uuid' => $user->getUuid(),
            'role' => $member->getRole()->value,
            'joined_at' => $member->getJoinedAt()->format(\DateTimeInterface::ATOM),
            'display_name' => $profile->getDisplayName() ?? $user->getEmail(),
        ];
    }
}
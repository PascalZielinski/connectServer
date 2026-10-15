<?php

namespace App\Service;

use App\Entity\Profile;
use App\Entity\ProfileImage;
use App\Entity\User;
use App\Repository\ProfileRepository;
use App\Security\OwnerVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class ProfileService
{

    private const MAX_SIZE = 10 * 1024 * 1024;
    private const MAX_DIMENSION = 8000;    
    private const ALLOWED = [
        'image/jpeg' => ['ext' => 'jpg', 'magic' => "\xFF\xD8\xFF"],
        'image/png'  => ['ext' => 'png', 'magic' => "\x89PNG\r\n\x1A\n"],
    ];

    public function __construct(
        private ProfileRepository $profiles,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        #[Autowire('%kernel.project_dir%/var/uploads')]
        private string $uploadDir,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    public function create(mixed $currentUser, array $data): OperationResult
    {
        $user = $this->authorizedUser($currentUser);
        if ($this->profiles->findOneBy(['user' => $user]) !== null) {
            return new OperationResult(['error' => 'A profile already exists.'], 409);
        }

        $profile = (new Profile())->setUser($user);
        $this->applyData($profile, $data);
        $errors = $this->validationErrors($profile);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return new OperationResult($this->profileData($profile), 201);
    }

    public function show(mixed $currentUser): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));

        return new OperationResult($this->profileData($profile));
    }

    public function uploadImage(mixed $currentUser, ?UploadedFile $image): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));

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

        $old = $profile->getProfileImage();
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
        $profileImage = $old ?? new ProfileImage();
        $profileImage->setName($filename);
        if ($old === null) {
            $profileImage->setProfile($profile);
            $this->entityManager->persist($profileImage);
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

        return new OperationResult($this->profileData($profile));
    }

    public function getImagePath(mixed $currentUser): ?string
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));

        $image = $profile->getProfileImage();
        if ($image === null) {
            return null;
        }

        $path = $this->uploadDir . '/' . basename($image->getName());

        return is_file($path) ? $path : null;
    }

    public function update(mixed $currentUser, array $data): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));
        $this->applyData($profile, $data);
        $errors = $this->validationErrors($profile);
        if ($errors !== []) {
            return new OperationResult(['errors' => $errors], 422);
        }

        $this->entityManager->flush();

        return new OperationResult($this->profileData($profile));
    }

    public function delete(mixed $currentUser): OperationResult
    {
        $profile = $this->findForUser($this->authorizedUser($currentUser));
        $this->entityManager->remove($profile);
        $this->entityManager->flush();

        return new OperationResult(null, 204);
    }

    private function authorizedUser(mixed $user): User
    {
        if (!$user instanceof User
            || !$this->authorization->isGranted('ROLE_USER')
            || !$this->authorization->isGranted(OwnerVoter::MANAGE, $user)) {
            throw new AccessDeniedHttpException();
        }

        return $user;
    }

    private function findForUser(User $user): Profile
    {
        $profile = $this->profiles->findOneBy(['user' => $user]);
        if (!$profile instanceof Profile) {
            throw new NotFoundHttpException();
        }

        return $profile;
    }

    private function applyData(Profile $profile, array $data): void
    {
        foreach (['display_name', 'bio'] as $field) {
            $key = $field;
            if (array_key_exists($key, $data)) {
                if ($data[$key] !== null && !is_string($data[$key])) {
                    throw new BadRequestHttpException(sprintf('%s must be a string or null.', $field));
                }
                if ($key === 'display_name') {
                    $profile->setDisplayName($data[$key]);
                } else {
                    $profile->setBio($data[$key]);
                }
            }
        }
    }

    private function validationErrors(Profile $profile): array
    {
        $errors = [];
        foreach ($this->validator->validate($profile) as $violation) {
            $errors[$violation->getPropertyPath()][] = $violation->getMessage();
        }

        return $errors;
    }

    private function profileData(Profile $profile): array
    {
        return [
            'id' => $profile->getId(),
            'user_id' => $profile->getUser()?->getId(),
            'display_name' => $profile->getDisplayName(),
            'bio' => $profile->getBio(),
        ];
    }
}

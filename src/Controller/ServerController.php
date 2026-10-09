<?php

namespace App\Controller;

use App\Entity\Member;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\MemberRole;
use App\Repository\MemberRepository;
use App\Repository\ServerRepository;
use App\Repository\UserRepository;
use App\Security\ServerVoter;
use App\Service\ServerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ServerController extends AbstractController
{
    #[Route('/servers', name: 'api_servers_create', methods: ['POST'])]
    public function create(Request $request, ServerService $serverService): JsonResponse
    {
        $data = $request->toArray();
        $error = $this->validateSettingsInput($data, true);
        if ($error !== null) {
            return $this->json(['error' => $error], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serverData($serverService->create($this->currentUser(), $data)), JsonResponse::HTTP_CREATED);
    }

    #[Route('/servers', name: 'api_servers_public', methods: ['GET'])]
    public function publicServers(Request $request, ServerRepository $servers): JsonResponse
    {
        $search = $request->query->get('q');
        if ($search !== null && !is_string($search)) {
            return $this->json(['error' => 'q must be a string.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $search = $search === null ? null : trim($search);
        if ($search === '') {
            return $this->json(['error' => 'q must not be empty.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json(array_map(
            fn (Server $server): array => $this->searchServerData($server),
            $servers->findPublicServers($search),
        ));
    }

    #[Route('/servers/search', name: 'api_servers_search', methods: ['GET'])]
    public function searchPublicServers(Request $request, ServerRepository $servers): JsonResponse
    {
        return $this->publicServers($request, $servers);
    }

    #[Route('/me/servers', name: 'api_me_servers', methods: ['GET'])]
    public function myServers(MemberRepository $members): JsonResponse
    {
        $servers = array_map(
            static fn (Member $member): Server => $member->getServer(),
            $members->findByUser($this->currentUser()),
        );

        return $this->json(array_map(fn (Server $server): array => $this->serverData($server), $servers));
    }

    #[Route('/servers/{uuid}', name: 'api_servers_show', methods: ['GET'])]
    public function show(string $uuid, ServerRepository $servers): JsonResponse
    {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::VIEW, $server);

        return $this->json($this->serverData($server));
    }

    #[Route('/servers/{uuid}', name: 'api_servers_update', methods: ['PATCH', 'PUT'])]
    public function update(
        string $uuid,
        Request $request,
        ServerRepository $servers,
        ServerService $serverService,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE, $server);
        $data = $request->toArray();
        $error = $this->validateSettingsInput($data, false);
        if ($error !== null) {
            return $this->json(['error' => $error], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json($this->serverData($serverService->updateSettings($server, $data)));
    }

    #[Route('/servers/{uuid}', name: 'api_servers_delete', methods: ['DELETE'])]
    public function delete(string $uuid, ServerRepository $servers, ServerService $serverService): JsonResponse
    {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE, $server);
        $serverService->delete($server);

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/servers/{uuid}/members', name: 'api_server_members_list', methods: ['GET'])]
    public function members(string $uuid, ServerRepository $servers, MemberRepository $members): JsonResponse
    {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::VIEW, $server);

        return $this->json(array_map(
            fn (Member $member): array => $this->memberData($member),
            $members->findByServer($server),
        ));
    }

    #[Route('/servers/{uuid}/members', name: 'api_server_members_join', methods: ['POST'])]
    public function join(
        string $uuid,
        ServerRepository $servers,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        return $this->joinServer($uuid, $servers, $members, $entityManager);
    }

    #[Route(
        '/servers/{uuid}/join',
        name: 'api_server_join',
        requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}'],
        methods: ['POST'],
    )]
    public function joinByUuid(
        string $uuid,
        ServerRepository $servers,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        return $this->joinServer($uuid, $servers, $members, $entityManager);
    }

    private function joinServer(
        string $uuid,
        ServerRepository $servers,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        if ($server->getSettings()?->isPublicy() !== true) {
            throw $this->createAccessDeniedException('Only public servers can be joined without an invitation.');
        }

        $user = $this->currentUser();
        if ($members->findMembership($server, $user) !== null) {
            return $this->json(['error' => 'You are already a member of this server.'], JsonResponse::HTTP_CONFLICT);
        }

        $member = new Member($server, $user);
        $entityManager->persist($member);
        $entityManager->flush();

        return $this->json($this->memberData($member), JsonResponse::HTTP_CREATED);
    }

    #[Route('/servers/{uuid}/members/{userUuid}', name: 'api_server_members_invite', methods: ['POST'])]
    public function invite(
        string $uuid,
        string $userUuid,
        Request $request,
        ServerRepository $servers,
        MemberRepository $members,
        UserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE_CHANNELS, $server);
        $data = $request->toArray();
        $roleValue = $data['role'] ?? MemberRole::Member->value;
        if (!is_string($roleValue) || !in_array($roleValue, [MemberRole::Member->value, MemberRole::Editor->value], true)) {
            return $this->json(['error' => 'Invited members can have the member or editor role.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        $inviter = $members->findMembership($server, $this->currentUser());
        if ($inviter?->getRole() === MemberRole::Editor && $roleValue !== MemberRole::Member->value) {
            throw $this->createAccessDeniedException('Editors can invite members but cannot assign editor roles.');
        }

        $user = $users->findOneBy(['uuid' => $userUuid]);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }
        if ($members->findMembership($server, $user) !== null) {
            return $this->json(['error' => 'This user is already a member of the server.'], JsonResponse::HTTP_CONFLICT);
        }

        $member = new Member($server, $user, MemberRole::from($roleValue));
        $entityManager->persist($member);
        $entityManager->flush();

        return $this->json($this->memberData($member), JsonResponse::HTTP_CREATED);
    }

    #[Route('/servers/{uuid}/members/me', name: 'api_server_members_leave', methods: ['DELETE'])]
    public function leave(
        string $uuid,
        ServerRepository $servers,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $member = $members->findMembership($server, $this->currentUser());
        if ($member === null) {
            throw $this->createNotFoundException('Membership not found.');
        }
        if ($member->getRole() === MemberRole::Owner && $members->countOwners($server) <= 1) {
            return $this->json(['error' => 'Transfer ownership before leaving this server.'], JsonResponse::HTTP_CONFLICT);
        }

        $entityManager->remove($member);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/servers/{uuid}/members/{userUuid}', name: 'api_server_members_update', methods: ['PATCH'])]
    public function updateMember(
        string $uuid,
        string $userUuid,
        Request $request,
        ServerRepository $servers,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE, $server);
        $member = $this->findMember($server, $userUuid, $members);
        $data = $request->toArray();
        if (!isset($data['role']) || !is_string($data['role']) || ($role = MemberRole::tryFrom($data['role'])) === null) {
            return $this->json(['error' => 'role must be one of: member, editor, owner.'], JsonResponse::HTTP_BAD_REQUEST);
        }
        if ($member->getRole() === MemberRole::Owner && $role !== MemberRole::Owner && $members->countOwners($server) <= 1) {
            return $this->json(['error' => 'A server must have at least one owner.'], JsonResponse::HTTP_CONFLICT);
        }

        $member->setRole($role);
        $entityManager->flush();

        return $this->json($this->memberData($member));
    }

    #[Route('/servers/{uuid}/members/{userUuid}', name: 'api_server_members_remove', methods: ['DELETE'])]
    public function removeMember(
        string $uuid,
        string $userUuid,
        ServerRepository $servers,
        MemberRepository $members,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $server = $this->findServer($uuid, $servers);
        $this->denyAccessUnlessGranted(ServerVoter::MANAGE, $server);
        $member = $this->findMember($server, $userUuid, $members);
        if ($member->getRole() === MemberRole::Owner && $members->countOwners($server) <= 1) {
            return $this->json(['error' => 'A server must have at least one owner.'], JsonResponse::HTTP_CONFLICT);
        }

        $entityManager->remove($member);
        $entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function findServer(string $uuid, ServerRepository $servers): Server
    {
        $server = $servers->findOneBy(['uuid' => $uuid]);
        if (!$server instanceof Server) {
            throw $this->createNotFoundException('Server not found.');
        }

        return $server;
    }

    private function findMember(Server $server, string $userUuid, MemberRepository $members): Member
    {
        $member = $members->createQueryBuilder('member')
            ->join('member.user', 'user')
            ->andWhere('member.server = :server')
            ->andWhere('user.uuid = :userUuid')
            ->setParameter('server', $server)
            ->setParameter('userUuid', $userUuid)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$member instanceof Member) {
            throw $this->createNotFoundException('Member not found.');
        }

        return $member;
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
        return [
            'user_uuid' => $member->getUser()?->getUuid(),
            'role' => $member->getRole()->value,
            'joined_at' => $member->getJoinedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

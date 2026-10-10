<?php

namespace App\Controller;

use App\Service\OperationResult;
use App\Service\ServerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
final class ServerController extends AbstractController
{
    #[Route('/servers', name: 'api_servers_create', methods: ['POST'])]
    public function create(Request $request, ServerService $servers): JsonResponse
    {
        return $this->response($servers->create($this->getUser(), $request->toArray()));
    }

    #[Route('/servers', name: 'api_servers_public', methods: ['GET'])]
    public function publicServers(Request $request, ServerService $servers): JsonResponse
    {
        return $this->response($servers->publicServers($request->query->get('q')));
    }

    #[Route('/servers/search', name: 'api_servers_search', methods: ['GET'])]
    public function searchPublicServers(Request $request, ServerService $servers): JsonResponse
    {
        return $this->response($servers->publicServers($request->query->get('q')));
    }

    #[Route('/me/servers', name: 'api_me_servers', methods: ['GET'])]
    public function myServers(ServerService $servers): JsonResponse
    {
        return $this->response($servers->myServers($this->getUser()));
    }

    #[Route('/servers/{uuid}', name: 'api_servers_show', methods: ['GET'])]
    public function show(string $uuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->show($uuid));
    }

    #[Route('/servers/{uuid}', name: 'api_servers_update', methods: ['PATCH', 'PUT'])]
    public function update(string $uuid, Request $request, ServerService $servers): JsonResponse
    {
        return $this->response($servers->updateSettings($uuid, $request->toArray()));
    }

    #[Route('/servers/{uuid}', name: 'api_servers_delete', methods: ['DELETE'])]
    public function delete(string $uuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->delete($uuid));
    }

    #[Route('/servers/{uuid}/members', name: 'api_server_members_list', methods: ['GET'])]
    public function members(string $uuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->members($uuid));
    }

    #[Route('/servers/{uuid}/members', name: 'api_server_members_join', methods: ['POST'])]
    public function join(string $uuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->join($uuid, $this->getUser()));
    }

    #[Route(
        '/servers/{uuid}/join',
        name: 'api_server_join',
        requirements: ['uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89aAbB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}'],
        methods: ['POST'],
    )]
    public function joinByUuid(string $uuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->join($uuid, $this->getUser()));
    }

    #[Route('/servers/{uuid}/members/{userUuid}', name: 'api_server_members_invite', methods: ['POST'])]
    public function invite(
        string $uuid,
        string $userUuid,
        Request $request,
        ServerService $servers,
    ): JsonResponse {
        return $this->response($servers->invite(
            $uuid,
            $userUuid,
            $this->getUser(),
            $request->toArray(),
        ));
    }

    #[Route('/servers/{uuid}/members/me', name: 'api_server_members_leave', methods: ['DELETE'])]
    public function leave(string $uuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->leave($uuid, $this->getUser()));
    }

    #[Route('/servers/{uuid}/members/{userUuid}', name: 'api_server_members_update', methods: ['PATCH'])]
    public function updateMember(
        string $uuid,
        string $userUuid,
        Request $request,
        ServerService $servers,
    ): JsonResponse {
        return $this->response($servers->updateMember($uuid, $userUuid, $request->toArray()));
    }

    #[Route('/servers/{uuid}/members/{userUuid}', name: 'api_server_members_remove', methods: ['DELETE'])]
    public function removeMember(string $uuid, string $userUuid, ServerService $servers): JsonResponse
    {
        return $this->response($servers->removeMember($uuid, $userUuid));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}

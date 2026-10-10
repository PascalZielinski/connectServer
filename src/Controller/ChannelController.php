<?php

namespace App\Controller;

use App\Service\ChannelService;
use App\Service\OperationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/servers/{uuid}/channels')]
final class ChannelController extends AbstractController
{
    #[Route('', name: 'api_server_channels_list', methods: ['GET'])]
    public function index(string $uuid, ChannelService $channels): JsonResponse
    {
        return $this->response($channels->index($uuid));
    }

    #[Route('', name: 'api_server_channels_create', methods: ['POST'])]
    public function create(string $uuid, Request $request, ChannelService $channels): JsonResponse
    {
        return $this->response($channels->create($uuid, $request->toArray()));
    }

    #[Route('/{id}', name: 'api_server_channels_update', requirements: ['id' => '\d+'], methods: ['PATCH', 'PUT'])]
    public function update(int $id, string $uuid, Request $request, ChannelService $channels): JsonResponse
    {
        return $this->response($channels->updateForServer($uuid, $id, $request->toArray()));
    }

    #[Route('/{id}', name: 'api_server_channels_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, string $uuid, ChannelService $channels): JsonResponse
    {
        return $this->response($channels->deleteForServer($uuid, $id));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}

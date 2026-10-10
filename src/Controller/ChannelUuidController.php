<?php

namespace App\Controller;

use App\Service\ChannelService;
use App\Service\OperationResult;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/channels/{channelUuid}')]
final class ChannelUuidController extends AbstractController
{
    #[Route('', name: 'api_channel_update', methods: ['PATCH'])]
    public function update(string $channelUuid, Request $request, ChannelService $channels): JsonResponse
    {
        return $this->response($channels->updateByUuid($channelUuid, $request->toArray()));
    }

    #[Route('', name: 'api_channel_delete', methods: ['DELETE'])]
    public function delete(string $channelUuid, ChannelService $channels): JsonResponse
    {
        return $this->response($channels->deleteByUuid($channelUuid));
    }

    private function response(OperationResult $result): JsonResponse
    {
        return $this->json($result->data, $result->statusCode);
    }
}

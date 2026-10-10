<?php

namespace App\Controller;

use App\Service\OperationResult;
use App\Service\VoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/voice')]
final class VoiceController extends AbstractController
{
    #[Route('/{channelUuid}/token', name: 'api_voice_channel_token', methods: ['POST'])]
    public function token(string $channelUuid, VoiceService $voice): JsonResponse
    {
        $result = $voice->createToken($channelUuid, $this->getUser());

        return $this->json($result->data, $result->statusCode);
    }
}

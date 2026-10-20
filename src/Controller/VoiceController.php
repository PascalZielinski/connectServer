<?php

namespace App\Controller;

use App\Repository\ServerRepository;
use App\Service\OperationResult;
use App\Service\VoiceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Uid\Uuid;

#[Route('/api/voice')]
final class VoiceController extends AbstractController
{
    
    #[Route('/{channelUuid}/token', name: 'api_voice_channel_token', methods: ['POST'])]
    public function token(string $channelUuid, VoiceService $voice): JsonResponse
    {
        $result = $voice->createToken($channelUuid, $this->getUser());

        return $this->json($result->data, $result->statusCode);
    }

    #[Route('/servers/{uuid}/voice-state', methods: ['GET'])]
    public function voiceState(string $uuid, ServerRepository $servers, VoiceService $voice): JsonResponse
    {
        $server = $servers->findOneBy(['uuid' => $uuid]);
        if ($server === null) {
            throw new NotFoundHttpException('Server not found.');
        }

        return $this->json($voice->getVoiceState($server, $this->getUser()));
    }

    #[Route('/webhook', name: 'api_voice_webhook', methods: ['POST'])]
    public function webhook(Request $request, VoiceService $voice): Response
    {
        $voice->handleWebhook($request->getContent(), $request->headers->get('Authorization'));

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/servers/{uuid}/mercure', name: 'api_voice_mercure_auth', methods: ['GET'])]
    public function mercureAuth(
        string $uuid,
        ServerRepository $servers,
        VoiceService $voice,
        #[Autowire(service: 'mercure.hub.default.jwt.factory')] TokenFactoryInterface $tokenFactory,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required.'], 401);
        }

        $server = $servers->findOneBy(['uuid' => $uuid]);
        if ($server === null) {
            throw new NotFoundHttpException('Server not found.');
        }

        $topic = $voice->subscriptionTopic($server, $user);
        $userUuid = $user->getUuid();
        $userUuid = $userUuid instanceof Uuid ? $userUuid->toRfc4122() : (string) $userUuid;

        return $this->json([
            'topic' => $topic,
            'token' => $tokenFactory->create(
                [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])],
                ['iss' => 'connect-server', 'aud' => 'mercure', 'sub' => $userUuid, 'client_id' => 'connect-client'],
            ),
        ]);
    }
}

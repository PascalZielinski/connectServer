<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\Jwt\Grant;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class MercureController extends AbstractController
{
    #[Route('/api/mercure/auth', name: 'api_mercure_auth', methods: ['GET'])]
    public function mercureAuth(
        #[Autowire(service: 'mercure.hub.default.jwt.factory')] TokenFactoryInterface $tokenFactory,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required.'], 401);
        }

        $uuid = $user->getUuid();
        $uuidString = $uuid instanceof Uuid ? $uuid->toRfc4122() : (string) $uuid;
        $topic = 'dm/' . $uuidString;

        return $this->json([
            'topic' => $topic,
            'token' => $tokenFactory->create(
                [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])],
                [
                    'iss' => 'connect-server',
                    'aud' => 'mercure',
                    'sub' => $uuidString,
                    'client_id' => 'connect-client',
                ],
            ),
        ]);
    }

    #[Route('/api/channels/{channelUuid}/mercure', name: 'api_channel_mercure_auth', methods: ['GET'])]
    public function channelMercureAuth(
        string $channelUuid,
        MessageService $messages,
        #[Autowire(service: 'mercure.hub.default.jwt.factory')] TokenFactoryInterface $tokenFactory,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Authentication required.'], 401);
        }

        if (!Uuid::isValid($channelUuid)) {
            return $this->json(['error' => 'channel must be a valid UUID.'], 400);
        }

        $topic = $messages->subscriptionTopic($channelUuid, $user);

        $uuid = $user->getUuid();
        $uuidString = $uuid instanceof Uuid ? $uuid->toRfc4122() : (string) $uuid;

        return $this->json([
            'topic' => $topic,
            'token' => $tokenFactory->create(
                [new Grant([Grant::ACTION_SUBSCRIBE], [$topic])],
                [
                    'iss' => 'connect-server',
                    'aud' => 'mercure',
                    'sub' => $uuidString,
                    'client_id' => 'connect-client',
                ],
            ),
        ]);
    }
}
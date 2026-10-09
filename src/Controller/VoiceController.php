<?php

namespace App\Controller;

use App\Entity\Server;
use App\Entity\User;
use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\MemberRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/voice')]
final class VoiceController extends AbstractController
{
    public function __construct(
        #[Autowire('%env(string:LIVEKIT_API_KEY)%')]
        private readonly string $lkApiKey,
        #[Autowire('%env(string:LIVEKIT_API_SECRET)%')]
        private readonly string $lkSecret,
        #[Autowire('%env(string:LIVEKIT_URL)%')]
        private readonly string $lkUrl,
    ) {
    }

    #[Route('/{channelUuid}/token', name: 'api_voice_channel_token', methods: ['POST'])]
    public function token(
        string $channelUuid,
        ChannelRepository $channels,
        MemberRepository $members,
    ): JsonResponse {
        $channel = $channels->findOneBy(['uuid' => $channelUuid]);
        if ($channel === null) {
            throw $this->createNotFoundException('Channel not found.');
        }

        $server = $channel->getServerSettings()?->getServer();
        $user = $this->getUser();
        if (!$server instanceof Server || !$user instanceof User || $members->findMembership($server, $user) === null) {
            throw $this->createAccessDeniedException();
        }
        if ($channel->getType() !== ChannelType::Voice) {
            return $this->json(['error' => 'Channel is not a voice channel.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->lkSecret),
        );
        $token = $configuration->builder()
            ->issuedBy($this->lkApiKey)
            ->identifiedBy($user->getUuid())
            ->issuedAt($now)
            ->expiresAt($now->add(new \DateInterval('PT1H')))
            ->withClaim('video', [
                'roomJoin' => true,
                'room' => 'voice-' . $channel->getUuid(),
                'canPublish' => true,
                'canSubscribe' => true,
            ])
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();

        return $this->json([
            'token' => $token,
            'url' => $this->lkUrl,
        ]);
    }
}

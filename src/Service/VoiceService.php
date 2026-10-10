<?php

namespace App\Service;

use App\Entity\Channel;
use App\Entity\Server;
use App\Entity\User;
use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use App\Repository\MemberRepository;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class VoiceService
{
    public function __construct(
        private ChannelRepository $channels,
        private MemberRepository $members,
        #[Autowire('%env(string:LIVEKIT_API_KEY)%')]
        private string $lkApiKey,
        #[Autowire('%env(string:LIVEKIT_API_SECRET)%')]
        private string $lkSecret,
        #[Autowire('%env(string:LIVEKIT_URL)%')]
        private string $lkUrl,
    ) {
    }

    public function createToken(string $channelUuid, mixed $currentUser): OperationResult
    {
        $channel = $this->channels->findOneBy(['uuid' => $channelUuid]);
        if (!$channel instanceof Channel) {
            throw new NotFoundHttpException('Channel not found.');
        }

        $server = $channel->getServerSettings()?->getServer();
        if (!$server instanceof Server || !$currentUser instanceof User || $this->members->findMembership($server, $currentUser) === null) {
            throw new AccessDeniedHttpException();
        }
        if ($channel->getType() !== ChannelType::Voice) {
            return new OperationResult(['error' => 'Channel is not a voice channel.'], 422);
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->lkSecret),
        );
        $token = $configuration->builder()
            ->issuedBy($this->lkApiKey)
            ->identifiedBy($currentUser->getUuid())
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

        return new OperationResult([
            'token' => $token,
            'url' => $this->lkUrl,
        ]);
    }
}

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
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class VoiceService
{
    public function __construct(
        private ChannelRepository $channels,
        private MemberRepository $members,
        private HttpClientInterface $http,
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

        /**
     * @return array{channels: array<string, list<string>>}
     */
    public function getVoiceState(Server $server, mixed $currentUser): array
    {
        if (!$currentUser instanceof User || $this->members->findMembership($server, $currentUser) === null) {
            throw new AccessDeniedHttpException();
        }

        $voiceChannels = $this->channels->createQueryBuilder('c')
            ->join('c.serverSettings', 's')
            ->andWhere('s.server = :server')
            ->andWhere('c.type = :type')
            ->setParameter('server', $server)
            ->setParameter('type', ChannelType::Voice)
            ->getQuery()
            ->getResult();

        $rooms = [];
        foreach ($voiceChannels as $channel) {
            $rooms['voice-' . $channel->getUuid()] = $channel->getUuid();
        }
        if ($rooms === []) {
            return ['channels' => []];
        }

        $list = $this->twirp('ListRooms', ['names' => array_keys($rooms)], ['roomList' => true]);

        $result = [];
        foreach ($list['rooms'] ?? [] as $room) {
            $name = $room['name'] ?? '';
            $count = (int) ($room['numParticipants'] ?? $room['num_participants'] ?? 0);
            if (!isset($rooms[$name]) || $count < 1) {
                continue;
            }

            $participants = $this->twirp(
                'ListParticipants',
                ['room' => $name],
                ['roomAdmin' => true, 'room' => $name],
            );

            $result[$rooms[$name]] = array_values(array_filter(
                array_map(static fn (array $p) => $p['identity'] ?? null, $participants['participants'] ?? []),
            ));
        }

        return ['channels' => $result];
    }

    private function twirp(string $method, array $body, array $grant): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->lkSecret),
        );
        $token = $configuration->builder()
            ->issuedBy($this->lkApiKey)
            ->issuedAt($now)
            ->expiresAt($now->add(new \DateInterval('PT1M')))
            ->withClaim('video', $grant)
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();

        $base = preg_replace('#^ws#', 'http', rtrim($this->lkUrl, '/'));

        try {
            $response = $this->http->request('POST', $base . '/twirp/livekit.RoomService/' . $method, [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => $body === [] ? new \stdClass() : $body,
                'timeout' => 3,
            ]);

            return $response->getStatusCode() === 200 ? $response->toArray(false) : [];
        } catch (\Throwable) {
            return [];
        }
    }
}

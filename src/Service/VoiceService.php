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
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

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
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    private const WEBHOOK_EVENTS = [
        'participant_joined',
        'participant_left',
        'track_published',
        'track_unpublished',
    ];

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

    public static function topic(Server $server): string
    {
        return sprintf('servers/%s/voice', $server->getUuid());
    }

    public function subscriptionTopic(Server $server, mixed $currentUser): string
    {
        if (!$currentUser instanceof User || $this->members->findMembership($server, $currentUser) === null) {
            throw new AccessDeniedHttpException();
        }

        return self::topic($server);
    }

    public function handleWebhook(string $body, ?string $authorization): void
    {
        $this->assertValidWebhook($body, $authorization);

        $payload = json_decode($body, true);
        $event = $payload['event'] ?? null;
        $room = $payload['room']['name'] ?? '';
        if (!in_array($event, self::WEBHOOK_EVENTS, true) || !str_starts_with($room, 'voice-')) {
            return;
        }

        $channel = $this->channels->findOneBy(['uuid' => substr($room, strlen('voice-'))]);
        $server = $channel instanceof Channel ? $channel->getServerSettings()?->getServer() : null;
        if ($server === null) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                self::topic($server),
                json_encode([
                    'type' => $event,
                    'channel' => (string) $channel->getUuid(),
                    'identity' => $payload['participant']['identity'] ?? null,
                ], JSON_THROW_ON_ERROR),
                private: true,
            ));
        } catch (\Throwable $exception) {

            $this->logger->error('Mercure publish failed.', ['exception' => $exception]);
        }
    }

    private function assertValidWebhook(string $body, ?string $authorization): void
    {
        $configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($this->lkSecret),
        );

        try {
            $token = $configuration->parser()->parse((string) $authorization);
            $valid = $token instanceof Plain
                && $configuration->validator()->validate(
                    $token,
                    new SignedWith($configuration->signer(), $configuration->verificationKey()),
                    new IssuedBy($this->lkApiKey),
                )

                && hash_equals(
                    (string) $token->claims()->get('sha256'),
                    base64_encode(hash('sha256', $body, true)),
                );
        } catch (\Throwable) {
            $valid = false;
        }

        if (!$valid) {
            throw new UnauthorizedHttpException('LiveKit', 'Invalid webhook signature.');
        }
    }
}

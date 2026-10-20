<?php

namespace App\Service;

use App\Entity\TrustedDevice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class TrustedDeviceService
{
    private const DEFAULT_TTL_DAYS = 30;
    private const MAX_DEVICES_PER_USER = 10;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function register(User $user, array $data): OperationResult
    {
        $deviceName = trim((string) ($data['deviceName'] ?? ''));
        $publicKey  = trim((string) ($data['publicKey']  ?? ''));

        if ($deviceName === '') {
            return new OperationResult(['error' => 'deviceName is required'], 422);
        }
        if ($publicKey === '') {
            return new OperationResult(['error' => 'publicKey is required'], 422);
        }

        $existingCount = $this->em->getRepository(TrustedDevice::class)
            ->count(['User' => $user]);

        if ($existingCount >= self::MAX_DEVICES_PER_USER) {
            return new OperationResult(['error' => 'Device limit reached'], 429);
        }

        $device = new TrustedDevice();
        $device->setUser($user);
        $device->setDeviceName($deviceName);
        $device->setPublicKey($publicKey);
        $device->setExpiredAt(
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('+' . self::DEFAULT_TTL_DAYS . ' days')
        );

        $this->em->persist($device);
        $this->em->flush();

        return new OperationResult($this->serialize($device), 201);
    }

    public function list(User $user): OperationResult
    {
        $devices = $this->em->getRepository(TrustedDevice::class)
            ->findBy(['User' => $user], ['createdAt' => 'DESC']);

        return new OperationResult(
            array_map(fn (TrustedDevice $d) => $this->serialize($d), $devices),
            200,
        );
    }

    public function revoke(User $user, int $id): OperationResult
    {
        $device = $this->em->getRepository(TrustedDevice::class)->find($id);

        if (!$device || $device->getUser()?->getId() !== $user->getId()) {

            return new OperationResult(['error' => 'Device not found'], 404);
        }

        $this->em->remove($device);
        $this->em->flush();

        return new OperationResult(['message' => 'Device revoked'], 200);
    }

    private function serialize(TrustedDevice $device): array
    {
        $now       = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $expiredAt = $device->getExpiredAt();

        return [
            'id'          => $device->getId(),
            'deviceName'  => $device->getDeviceName(),
            'deviceUuid'  => $device->getDeviceUuid() ? (string) $device->getDeviceUuid() : null,
            'publicKey'   => $device->getPublicKey(),
            'createdAt'   => $device->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'expiredAt'   => $expiredAt?->format(\DateTimeInterface::ATOM),
            'lastLoginAt' => $device->getLastLoginAt()?->format(\DateTimeInterface::ATOM),
            'isExpired'   => $expiredAt !== null && $expiredAt < $now,
        ];
    }
}
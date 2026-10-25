<?php

namespace App\Entity;

use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TrustedDeviceRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_device_user_secret', columns: ['user_id', 'device_secret_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_device_confirmation_token', columns: ['confirmation_token_hash'])]
class TrustedDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trustedDevices')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $deviceName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $deviceUuid = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 64)]
    private string $deviceSecretHash = '';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $confirmationTokenHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmationTokenExpiresAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(length: 255)]
    private ?string $ip = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $attemptAt = null;

    public function __construct()
    {
        $this->deviceUuid = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->lastLoginAt = $this->createdAt;
        $this->confirmationTokenExpiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function startConfirmation(string $tokenHash, \DateTimeImmutable $tokenValidUntil): static
    {
        $this->confirmedAt = null;
        $this->expiresAt = null;
        $this->confirmationTokenHash = $tokenHash;
        $this->confirmationTokenExpiresAt = $tokenValidUntil;

        return $this;
    }

    public function confirm(\DateTimeImmutable $trustedUntil): static
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $this->confirmedAt = $now;
        $this->lastLoginAt = $now;
        $this->expiresAt = $trustedUntil;

        $this->confirmationTokenHash = null;
        $this->confirmationTokenExpiresAt = null;

        return $this;
    }

    public function isPending(): bool
    {
        return $this->confirmedAt === null;
    }

    public function isConfirmationTokenExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->confirmationTokenExpiresAt === null
            || ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) >= $this->confirmationTokenExpiresAt;
    }

    public function isTrusted(?\DateTimeImmutable $now = null): bool
    {
        return $this->confirmedAt !== null
            && $this->expiresAt !== null
            && ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) < $this->expiresAt;
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getDeviceName(): ?string { return $this->deviceName; }
    public function setDeviceName(string $deviceName): static { $this->deviceName = $deviceName; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getDeviceUuid(): ?Uuid { return $this->deviceUuid; }
    public function setDeviceUuid(Uuid $deviceUuid): static { $this->deviceUuid = $deviceUuid; return $this; }

    public function getLastLoginAt(): ?\DateTimeImmutable { return $this->lastLoginAt; }
    public function setLastLoginAt(\DateTimeImmutable $lastLoginAt): static { $this->lastLoginAt = $lastLoginAt; return $this; }

    public function getDeviceSecretHash(): string { return $this->deviceSecretHash; }
    public function setDeviceSecretHash(string $hash): static { $this->deviceSecretHash = $hash; return $this; }

    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }

    public function getConfirmationTokenHash(): ?string { return $this->confirmationTokenHash; }
    public function getConfirmationTokenExpiresAt(): ?\DateTimeImmutable { return $this->confirmationTokenExpiresAt; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function setIp(string $ip): static
    {
        $this->ip = $ip;

        return $this;
    }

    public function getAttemptAt(): ?\DateTimeImmutable
    {
        return $this->attemptAt;
    }

    public function setAttemptAt(\DateTimeImmutable $attemptAt): static
    {
        $this->attemptAt = $attemptAt;

        return $this;
    }
}
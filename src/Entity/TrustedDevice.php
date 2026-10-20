<?php

namespace App\Entity;

use App\Repository\TrustedDeviceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TrustedDeviceRepository::class)]
class TrustedDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'trustedDevices')]
    private ?User $User = null;

    #[ORM\Column(length: 255)]
    private ?string $DeviceName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expiredAt = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $deviceUuid = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(length: 255)]
    private ?string $PublicKey = null;

    public function __construct()
    {
        $this->deviceUuid = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User): static
    {
        $this->User = $User;

        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->DeviceName;
    }

    public function setDeviceName(string $DeviceName): static
    {
        $this->DeviceName = $DeviceName;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getExpiredAt(): ?\DateTimeImmutable
    {
        return $this->expiredAt;
    }

    public function setExpiredAt(\DateTimeImmutable $expiredAt): static
    {
        $this->expiredAt = $expiredAt;

        return $this;
    }

    public function getDeviceUuid(): ?Uuid
    {
        return $this->deviceUuid;
    }

    public function setDeviceUuid(Uuid $deviceUuid): static
    {
        $this->deviceUuid = $deviceUuid;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getPublicKey(): ?string
    {
        return $this->PublicKey;
    }

    public function setPublicKey(string $PublicKey): static
    {
        $this->PublicKey = $PublicKey;

        return $this;
    }
}

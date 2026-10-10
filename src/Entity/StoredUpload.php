<?php

namespace App\Entity;

use App\Repository\StoredUploadRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: StoredUploadRepository::class)]
#[ORM\Table(name: 'stored_uploads')]
#[ORM\UniqueConstraint(name: 'uniq_stored_uploads_uuid', columns: ['uuid'])]
#[ORM\Index(name: 'idx_stored_uploads_expires_at', columns: ['expires_at'])]
class StoredUpload
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 127)]
    private string $mediaType;

    #[ORM\Column]
    private bool $image;

    public function __construct(string $originalFilename, string $mediaType, bool $image)
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->uploadedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->expiresAt = $this->uploadedAt->add(new \DateInterval('PT24H'));
        $this->originalFilename = $originalFilename;
        $this->mediaType = $mediaType;
        $this->image = $image;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function isImage(): bool
    {
        return $this->image;
    }
}

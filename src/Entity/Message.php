<?php

namespace App\Entity;

use App\Repository\MessageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'messages')]
#[ORM\UniqueConstraint(name: 'uniq_messages_uuid', columns: ['uuid'])]
#[ORM\Index(name: 'idx_messages_channel_created', columns: ['channel_id', 'created_at', 'id'])]
#[ORM\Index(name: 'idx_messages_author', columns: ['author_id'])]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: Channel::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Channel $channel = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Assert\Length(max: 4000)]
    private string $content;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Channel $channel, User $author, string $content)
    {
        $uuidBytes = random_bytes(16);
        $uuidBytes[6] = chr((ord($uuidBytes[6]) & 0x0f) | 0x40);
        $uuidBytes[8] = chr((ord($uuidBytes[8]) & 0x3f) | 0x80);
        $uuid = bin2hex($uuidBytes);
        $this->uuid = sprintf(
            '%s-%s-%s-%s-%s',
            substr($uuid, 0, 8),
            substr($uuid, 8, 4),
            substr($uuid, 12, 4),
            substr($uuid, 16, 4),
            substr($uuid, 20),
        );
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->channel = $channel;
        $this->author = $author;
        $this->content = trim($content);
        $channel->addMessage($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getChannel(): ?Channel
    {
        return $this->channel;
    }

    public function setChannel(Channel $channel): self
    {
        $this->channel = $channel;
        if (!$channel->getMessages()->contains($this)) {
            $channel->addMessage($this);
        }

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): self
    {
        $this->content = trim($content);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt->setTimezone(new \DateTimeZone('Europe/Berlin'));
    }
}

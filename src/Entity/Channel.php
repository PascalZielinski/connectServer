<?php

namespace App\Entity;

use App\Enum\ChannelType;
use App\Repository\ChannelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChannelRepository::class)]
#[ORM\Table(name: 'channels')]
#[ORM\UniqueConstraint(name: 'uniq_channels_uuid', columns: ['uuid'])]
class Channel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\ManyToOne(targetEntity: ServerSettings::class, inversedBy: 'channels')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ServerSettings $serverSettings = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $name = '';

    #[ORM\Column(enumType: ChannelType::class)]
    private ChannelType $type;

    /** @var Collection<int, Message> */
    #[ORM\OneToMany(mappedBy: 'channel', targetEntity: Message::class, cascade: ['remove'], orphanRemoval: true)]
    private Collection $messages;

    public function __construct(ServerSettings $serverSettings, string $name, ChannelType $type)
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
        $this->messages = new ArrayCollection();
        $this->type = $type;
        $this->setName($name);
        $this->setServerSettings($serverSettings);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getServerSettings(): ?ServerSettings
    {
        return $this->serverSettings;
    }

    public function setServerSettings(?ServerSettings $serverSettings): self
    {
        $this->serverSettings = $serverSettings;
        if ($serverSettings !== null && !$serverSettings->getChannels()->contains($this)) {
            $serverSettings->addChannel($this);
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getType(): ChannelType
    {
        return $this->type;
    }

    public function setType(ChannelType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /** @return Collection<int, Message> */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            if ($message->getChannel() !== $this) {
                $message->setChannel($this);
            }
        }

        return $this;
    }
}

<?php

namespace App\Entity;

use App\Repository\ServerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ServerRepository::class)]
#[ORM\Table(name: 'servers')]
#[ORM\UniqueConstraint(name: 'uniq_servers_uuid', columns: ['uuid'])]
class Server
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $uuid;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $createdBy = null;

    #[ORM\OneToOne(mappedBy: 'server', targetEntity: ServerSettings::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?ServerSettings $settings = null;

    /** @var Collection<int, Member> */
    #[ORM\OneToMany(mappedBy: 'server', targetEntity: Member::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->members = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt->setTimezone(new \DateTimeZone('Europe/Berlin'));
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $user): self
    {
        $this->createdBy = $user;

        return $this;
    }

    public function getSettings(): ?ServerSettings
    {
        return $this->settings;
    }

    public function setSettings(?ServerSettings $settings): self
    {
        if ($settings === null && $this->settings !== null) {
            $this->settings->setServer(null);
        } elseif ($settings !== null && $settings->getServer() !== $this) {
            $settings->setServer($this);
        }

        $this->settings = $settings;

        return $this;
    }

    /** @return Collection<int, Member> */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(Member $member): self
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            if ($member->getServer() !== $this) {
                $member->setServer($this);
            }
        }

        return $this;
    }

    public function removeMember(Member $member): self
    {
        if ($this->members->removeElement($member) && $member->getServer() === $this) {
            $member->setServer(null);
        }

        return $this;
    }

    public function getMemberCount(): int
    {
        return $this->members->count();
    }
}

<?php

namespace App\Entity;

use App\Enum\MemberRole;
use App\Repository\MemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Table(name: 'members')]
#[ORM\UniqueConstraint(name: 'uniq_members_server_user', columns: ['server_id', 'user_id'])]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Server::class, inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Server $server = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(enumType: MemberRole::class)]
    private MemberRole $role;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct(Server $server, User $user, MemberRole $role = MemberRole::Member)
    {
        $this->joinedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->role = $role;
        $this->setServer($server);
        $this->user = $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): self
    {
        $this->server = $server;
        if ($server !== null && !$server->getMembers()->contains($this)) {
            $server->addMember($this);
        }

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getRole(): MemberRole
    {
        return $this->role;
    }

    public function setRole(MemberRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt->setTimezone(new \DateTimeZone('Europe/Berlin'));
    }
}

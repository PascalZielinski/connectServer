<?php

namespace App\Entity;

use App\Repository\DmParticipantRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DmParticipantRepository::class)]
#[ORM\UniqueConstraint(columns: ['conversation_id', 'user_id'])]
class DmParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DmConversation $conversation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $hidden = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?DmConversation
    {
        return $this->conversation;
    }

    public function setConversation(?DmConversation $conversation): static
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }
}
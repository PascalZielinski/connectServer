<?php

namespace App\Entity;

use App\Repository\DmConversationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: DmConversationRepository::class)]
class DmConversation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $uuid = null;

    /** @var Collection<int, DmParticipant> */
    #[ORM\OneToMany(targetEntity: DmParticipant::class, mappedBy: 'conversation', cascade: ['persist'], orphanRemoval: true)]
    private Collection $participants;

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->participants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?Uuid
    {
        return $this->uuid;
    }

    public function setUuid(Uuid $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    /** @return Collection<int, DmParticipant> */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(DmParticipant $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setConversation($this);
        }

        return $this;
    }

    public function removeParticipant(DmParticipant $participant): static
    {
        $this->participants->removeElement($participant);

        return $this;
    }
}
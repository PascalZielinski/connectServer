<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_users_uuid', columns: ['uuid'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36, unique: true)]
    private string $uuid;

    #[Assert\NotBlank(groups: ['registration'])]
    #[Assert\Length(min: 8, max: 128, groups: ['registration'])]
    #[Assert\NotCompromisedPassword(skipOnError: true, groups: ['registration'])]
    private ?string $plainPassword = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    private string $email = '';

    #[ORM\Column]
    #[Assert\Length(min: 8, max: 4096)]
    private string $password = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Profile::class, cascade: ['persist', 'remove'])]
    private ?Profile $profile = null;

    /**
     * @var Collection<int, TrustedDevice>
     */
    #[ORM\OneToMany(targetEntity: TrustedDevice::class, mappedBy: 'User')]
    private Collection $trustedDevices;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $IsVerifiedExpiresAt = null;

    public const VERIFY_TTL = '+30 minutes';

    public function __construct()
    {
        $this->uuid = Uuid::v4();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->roles = ['ROLE_USER'];
        $this->trustedDevices = new ArrayCollection();
        $this->IsVerifiedExpiresAt = new \DateTimeImmutable(self::VERIFY_TTL, new \DateTimeZone('UTC'));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt->setTimezone(new \DateTimeZone('Europe/Berlin'));
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): self
    {
        if ($profile === null && $this->profile !== null) {
            $this->profile->setUser(null);
        } elseif ($profile !== null && $profile->getUser() !== $this) {
            $profile->setUser($this);
        }

        $this->profile = $profile;

        return $this;
    }

    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = array_values(array_unique(array_filter(
            $roles,
            static fn (mixed $role): bool => is_string($role) && $role !== '',
        )));

        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function setPasswordHash(string $passwordHash): self
    {
        $this->password = $passwordHash;

        return $this;
    }
    /**
     * @return Collection<int, TrustedDevice>
     */
    public function getTrustedDevices(): Collection
    {
        return $this->trustedDevices;
    }

    public function addTrustedDevice(TrustedDevice $trustedDevice): static
    {
        if (!$this->trustedDevices->contains($trustedDevice)) {
            $this->trustedDevices->add($trustedDevice);
        }

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function getIsVerifiedExpiresAt(): ?\DateTimeImmutable
    {
        return $this->IsVerifiedExpiresAt;
    }

    public function setIsVerifiedExpiresAt(?\DateTimeImmutable $IsVerifiedExpiresAt): static
    {
        $this->IsVerifiedExpiresAt = $IsVerifiedExpiresAt;

        return $this;
    }
}

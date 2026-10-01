<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameDifficulty;
use App\Enum\OdtMatrix;
use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[UniqueEntity(fields: [
    'username',
], message: 'user.username_taken', repositoryMethod: 'findNonDeletedBy', errorPath: 'username', ignoreNull: false)]
#[UniqueEntity(fields: [
    'mail',
], message: 'user.email_taken', repositoryMethod: 'findNonDeletedBy', errorPath: 'mail', ignoreNull: true)]
#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'username', type: 'string', length: 180, unique: true)]
    private ?string $username = null;

    /**
     * @var array<string>
     */
    #[ORM\Column(name: 'roles', type: 'json')]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(name: 'password', type: 'string')]
    private ?string $password = null;

    #[ORM\Column(name: 'mail', type: 'string', length: 255, unique: true, nullable: true)]
    private ?string $mail = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(name: 'deleted_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\Column(name: 'last_login_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    /**
     * @var Collection<int, SpicyMatch>
     */
    #[ORM\OneToMany(targetEntity: SpicyMatch::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $spicyMatches;

    #[ORM\OneToOne(targetEntity: UserProgression::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserProgression $progression = null;

    #[ORM\OneToOne(targetEntity: UserStat::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?UserStat $stats = null;

    #[ORM\Column(enumType: GameDifficulty::class, options: [
        'default' => 'easy',
    ])]
    private GameDifficulty $preferredDifficulty = GameDifficulty::EASY;

    #[ORM\Column(name: 'locale', type: 'string', length: 5, options: [
        'default' => 'fr',
    ])]
    private string $locale = 'fr';

    #[ORM\Column(enumType: OdtMatrix::class, options: [
        'default' => 'air',
    ])]
    private OdtMatrix $defaultMatrix = OdtMatrix::AIR;

    #[ORM\Column(name: 'premium_until', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $premiumUntil = null;

    /**
     * @var Collection<int, Spices>
     */
    #[ORM\ManyToMany(targetEntity: Spices::class)]
    #[ORM\JoinTable(name: 'users_excluded_spices')]
    private Collection $excludedSpices;

    public function __construct()
    {
        $this->spicyMatches = new ArrayCollection();
        $this->excludedSpices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $this->created_at ??= new \DateTimeImmutable();
        $this->updated_at ??= new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeInterface $updated_at): self
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(?\DateTimeInterface $deleted_at): self
    {
        $this->deleted_at = $deleted_at;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getSalt(): ?string
    {
        return null;
    }

    public function getMail(): ?string
    {
        return $this->mail;
    }

    public function setMail(?string $mail): self
    {
        $this->mail = $mail;

        return $this;
    }

    /**
     * @return Collection<int, SpicyMatch>
     */
    public function getSpicyMatches(): Collection
    {
        return $this->spicyMatches;
    }

    public function getProgression(): ?UserProgression
    {
        return $this->progression;
    }

    public function setProgression(?UserProgression $progression): static
    {
        $this->progression = $progression;

        return $this;
    }

    public function getStats(): ?UserStat
    {
        return $this->stats;
    }

    public function setStats(?UserStat $stats): static
    {
        $this->stats = $stats;

        return $this;
    }

    public function addSpicyMatch(SpicyMatch $spicyMatch): static
    {
        if (! $this->spicyMatches->contains($spicyMatch)) {
            $this->spicyMatches->add($spicyMatch);
            $spicyMatch->setUserId($this);
        }

        return $this;
    }

    public function removeSpicyMatch(SpicyMatch $spicyMatch): static
    {
        if ($this->spicyMatches->removeElement($spicyMatch) && $spicyMatch->getUserId() === $this) {
            $spicyMatch->setUserId(null);
        }

        return $this;
    }

    public function getPreferredDifficulty(): GameDifficulty
    {
        return $this->preferredDifficulty;
    }

    public function setPreferredDifficulty(GameDifficulty $preferredDifficulty): static
    {
        $this->preferredDifficulty = $preferredDifficulty;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getPremiumUntil(): ?\DateTimeImmutable
    {
        return $this->premiumUntil;
    }

    public function setPremiumUntil(?\DateTimeImmutable $premiumUntil): static
    {
        $this->premiumUntil = $premiumUntil;

        return $this;
    }

    public function isPremium(?\DateTimeImmutable $now = null): bool
    {
        return $this->premiumUntil instanceof \DateTimeImmutable && $this->premiumUntil > ($now ?? new \DateTimeImmutable());
    }

    public function getDefaultMatrix(): OdtMatrix
    {
        return $this->defaultMatrix;
    }

    public function setDefaultMatrix(OdtMatrix $defaultMatrix): static
    {
        $this->defaultMatrix = $defaultMatrix;

        return $this;
    }

    /**
     * @return Collection<int, Spices>
     */
    public function getExcludedSpices(): Collection
    {
        return $this->excludedSpices;
    }

    public function addExcludedSpice(Spices $spice): static
    {
        if (! $this->excludedSpices->contains($spice)) {
            $this->excludedSpices->add($spice);
        }

        return $this;
    }

    public function removeExcludedSpice(Spices $spice): static
    {
        $this->excludedSpices->removeElement($spice);

        return $this;
    }
}

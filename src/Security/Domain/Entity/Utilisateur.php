<?php

namespace App\Security\Domain\Entity;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Organisation\Domain\Entity\Service;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Shared\Domain\Trait\IsActiveTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_utilisateur')]
#[ORM\HasLifecycleCallbacks]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    use IsActiveTrait;
    use CommonFieldsTrait;

    public const ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['utilisateur:read', 'ref'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Groups(['utilisateur:read', 'ref'])]
    private ?string $email = null;

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Groups(['utilisateur:read', 'ref'])]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Groups(['utilisateur:read', 'ref'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['utilisateur:read'])]
    private ?string $telephone = null;

    /** Rôles Symfony techniques (ROLE_SUPER_ADMIN) ; les droits métier viennent de $role. */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['utilisateur:read'])]
    private ?Role $role = null;

    /** Centres accessibles ; vide = tous les centres de l'entreprise. */
    #[ORM\ManyToMany(targetEntity: Centre::class)]
    #[ORM\JoinTable(name: 'gst_utilisateur_centre')]
    #[Groups(['utilisateur:read'])]
    private Collection $centres;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['utilisateur:read'])]
    private ?Service $service = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $loginCount = 0;

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['utilisateur:read'])]
    private bool $mustChangePassword = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['utilisateur:read'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    public function __construct()
    {
        $this->centres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    #[Groups(['utilisateur:read', 'ref'])]
    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique($roles));
        return $this;
    }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;
        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;
        return $this;
    }

    /** @return Collection<int, Centre> */
    public function getCentres(): Collection
    {
        return $this->centres;
    }

    /** @param iterable<Centre> $centres */
    public function setCentres(iterable $centres): static
    {
        $this->centres->clear();
        foreach ($centres as $centre) {
            $this->centres->add($centre);
        }
        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;
        return $this;
    }

    public function getLoginCount(): int
    {
        return $this->loginCount;
    }

    public function incrementLoginCount(): static
    {
        $this->loginCount++;
        $this->lastLoginAt = new \DateTimeImmutable();
        return $this;
    }

    public function isMustChangePassword(): bool
    {
        return $this->mustChangePassword;
    }

    public function setMustChangePassword(bool $mustChangePassword): static
    {
        $this->mustChangePassword = $mustChangePassword;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function eraseCredentials(): void
    {
    }
}

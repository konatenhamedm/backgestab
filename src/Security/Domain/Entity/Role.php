<?php

namespace App\Security\Domain\Entity;

use App\Organisation\Domain\Entity\Entreprise;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Shared\Domain\Trait\IsActiveTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_role', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_gst_role_entreprise_code', columns: ['entreprise_id', 'code'])])]
#[ORM\HasLifecycleCallbacks]
class Role
{
    use CommonFieldsTrait;
    use IsActiveTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['role:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(length: 100)]
    #[Groups(['role:read', 'ref'])]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Groups(['role:read', 'ref'])]
    private ?string $nom = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['role:read'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['role:read'])]
    private bool $systeme = false;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['role:read'])]
    private array $permissions = [];

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['role:read'])]
    private array $actions = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isSysteme(): bool
    {
        return $this->systeme;
    }

    public function setSysteme(bool $systeme): static
    {
        $this->systeme = $systeme;
        return $this;
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): static
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function getActions(): array
    {
        return $this->actions;
    }

    public function setActions(array $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

}

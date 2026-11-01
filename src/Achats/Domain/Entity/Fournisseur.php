<?php

namespace App\Achats\Domain\Entity;

use App\Organisation\Domain\Entity\Entreprise;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Shared\Domain\Trait\IsActiveTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_fournisseur')]
#[ORM\HasLifecycleCallbacks]
class Fournisseur
{
    use CommonFieldsTrait;
    use IsActiveTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['fournisseur:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(length: 255)]
    #[Groups(['fournisseur:read', 'ref'])]
    private ?string $raisonSociale = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['fournisseur:read'])]
    private ?string $contactNom = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['fournisseur:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['fournisseur:read'])]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['fournisseur:read'])]
    private ?string $adresse = null;

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

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(?string $raisonSociale): static
    {
        $this->raisonSociale = $raisonSociale;
        return $this;
    }

    public function getContactNom(): ?string
    {
        return $this->contactNom;
    }

    public function setContactNom(?string $contactNom): static
    {
        $this->contactNom = $contactNom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
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

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

}

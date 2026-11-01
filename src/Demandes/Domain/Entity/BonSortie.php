<?php

namespace App\Demandes\Domain\Entity;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_bon_sortie')]
#[ORM\HasLifecycleCallbacks]
class BonSortie
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bonSortie:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Centre $centre = null;

    #[ORM\ManyToOne(targetEntity: DemandeMateriel::class, inversedBy: 'bonsSortie')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DemandeMateriel $demande = null;

    #[ORM\Column(length: 50)]
    #[Groups(['bonSortie:read', 'ref'])]
    private ?string $numeroBon = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['bonSortie:read'])]
    private ?\DateTimeImmutable $dateLivraison = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['bonSortie:read'])]
    private ?Utilisateur $livrePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['bonSortie:read'])]
    private ?\DateTimeImmutable $dateAccuseReception = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['bonSortie:read'])]
    private ?string $confirmation = null;

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

    public function getCentre(): ?Centre
    {
        return $this->centre;
    }

    public function setCentre(?Centre $centre): static
    {
        $this->centre = $centre;
        return $this;
    }

    public function getDemande(): ?DemandeMateriel
    {
        return $this->demande;
    }

    public function setDemande(?DemandeMateriel $demande): static
    {
        $this->demande = $demande;
        return $this;
    }

    public function getNumeroBon(): ?string
    {
        return $this->numeroBon;
    }

    public function setNumeroBon(?string $numeroBon): static
    {
        $this->numeroBon = $numeroBon;
        return $this;
    }

    public function getDateLivraison(): ?\DateTimeImmutable
    {
        return $this->dateLivraison;
    }

    public function setDateLivraison(?\DateTimeImmutable $dateLivraison): static
    {
        $this->dateLivraison = $dateLivraison;
        return $this;
    }

    public function getLivrePar(): ?Utilisateur
    {
        return $this->livrePar;
    }

    public function setLivrePar(?Utilisateur $livrePar): static
    {
        $this->livrePar = $livrePar;
        return $this;
    }

    public function getDateAccuseReception(): ?\DateTimeImmutable
    {
        return $this->dateAccuseReception;
    }

    public function setDateAccuseReception(?\DateTimeImmutable $dateAccuseReception): static
    {
        $this->dateAccuseReception = $dateAccuseReception;
        return $this;
    }

    public function getConfirmation(): ?string
    {
        return $this->confirmation;
    }

    public function setConfirmation(?string $confirmation): static
    {
        $this->confirmation = $confirmation;
        return $this;
    }

}

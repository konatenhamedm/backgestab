<?php

namespace App\Demandes\Domain\Entity;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Organisation\Domain\Entity\Service;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_demande_materiel')]
#[ORM\HasLifecycleCallbacks]
class DemandeMateriel
{
    use CommonFieldsTrait;

    public const EN_ATTENTE = 'EN_ATTENTE';
    public const VALIDEE = 'VALIDEE';
    public const REFUSEE = 'REFUSEE';
    public const EN_LIVRAISON = 'EN_LIVRAISON';
    public const LIVREE = 'LIVREE';


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['demandeMateriel:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Centre $centre = null;

    #[ORM\Column(length: 50)]
    #[Groups(['demandeMateriel:read', 'ref'])]
    private ?string $numeroDemande = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['demandeMateriel:read'])]
    private ?Utilisateur $demandeur = null;

    #[ORM\ManyToOne(targetEntity: Service::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['demandeMateriel:read'])]
    private ?Service $service = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['demandeMateriel:read'])]
    private ?\DateTimeImmutable $dateDemande = null;

    #[ORM\Column(length: 30, options: ['default' => 'EN_ATTENTE'])]
    #[Groups(['demandeMateriel:read', 'ref'])]
    private ?string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['demandeMateriel:read'])]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['demandeMateriel:read'])]
    private ?string $motifRefus = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['demandeMateriel:read'])]
    private ?Utilisateur $valideur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['demandeMateriel:read'])]
    private ?\DateTimeImmutable $dateValidation = null;

    #[ORM\OneToMany(mappedBy: 'demande', targetEntity: LigneDemande::class, cascade: ['persist','remove'], orphanRemoval: true)]
    #[Groups(['demandeMateriel:read'])]
    private Collection $lignes;

    #[ORM\OneToMany(mappedBy: 'demande', targetEntity: BonSortie::class, cascade: ['remove'], orphanRemoval: true)]
    #[Groups(['demandeMateriel:read'])]
    private Collection $bonsSortie;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
        $this->bonsSortie = new ArrayCollection();
    }

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

    public function getNumeroDemande(): ?string
    {
        return $this->numeroDemande;
    }

    public function setNumeroDemande(?string $numeroDemande): static
    {
        $this->numeroDemande = $numeroDemande;
        return $this;
    }

    public function getDemandeur(): ?Utilisateur
    {
        return $this->demandeur;
    }

    public function setDemandeur(?Utilisateur $demandeur): static
    {
        $this->demandeur = $demandeur;
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

    public function getDateDemande(): ?\DateTimeImmutable
    {
        return $this->dateDemande;
    }

    public function setDateDemande(?\DateTimeImmutable $dateDemande): static
    {
        $this->dateDemande = $dateDemande;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;
        return $this;
    }

    public function getMotifRefus(): ?string
    {
        return $this->motifRefus;
    }

    public function setMotifRefus(?string $motifRefus): static
    {
        $this->motifRefus = $motifRefus;
        return $this;
    }

    public function getValideur(): ?Utilisateur
    {
        return $this->valideur;
    }

    public function setValideur(?Utilisateur $valideur): static
    {
        $this->valideur = $valideur;
        return $this;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    /** @return Collection<int, LigneDemande> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneDemande $item): static
    {
        if (!$this->lignes->contains($item)) {
            $this->lignes->add($item);
            $item->setDemande($this);
        }
        return $this;
    }

    public function clearLignes(): static
    {
        $this->lignes->clear();
        return $this;
    }

    /** @return Collection<int, BonSortie> */
    public function getBonsSortie(): Collection
    {
        return $this->bonsSortie;
    }

    public function addBonsSortie(BonSortie $item): static
    {
        if (!$this->bonsSortie->contains($item)) {
            $this->bonsSortie->add($item);
            $item->setDemande($this);
        }
        return $this;
    }

    public function clearBonsSortie(): static
    {
        $this->bonsSortie->clear();
        return $this;
    }

}

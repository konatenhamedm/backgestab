<?php

namespace App\Achats\Domain\Entity;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_bon_reception')]
#[ORM\HasLifecycleCallbacks]
class BonReception
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bonReception:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Centre $centre = null;

    #[ORM\Column(length: 50)]
    #[Groups(['bonReception:read', 'ref'])]
    private ?string $numeroBon = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['bonReception:read'])]
    private ?string $numeroBonLivraison = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['bonReception:read'])]
    private ?Fournisseur $fournisseur = null;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['bonReception:read'])]
    private ?Commande $commande = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['bonReception:read'])]
    private ?\DateTimeImmutable $dateEntree = null;

    #[ORM\Column(length: 30, options: ['default' => 'VALIDE'])]
    #[Groups(['bonReception:read'])]
    private ?string $statut = 'VALIDE';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['bonReception:read'])]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'bonReception', targetEntity: LigneBonReception::class, cascade: ['persist','remove'], orphanRemoval: true)]
    #[Groups(['bonReception:read'])]
    private Collection $lignes;

    public function __construct()
    {
        $this->lignes = new ArrayCollection();
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

    public function getNumeroBon(): ?string
    {
        return $this->numeroBon;
    }

    public function setNumeroBon(?string $numeroBon): static
    {
        $this->numeroBon = $numeroBon;
        return $this;
    }

    public function getNumeroBonLivraison(): ?string
    {
        return $this->numeroBonLivraison;
    }

    public function setNumeroBonLivraison(?string $numeroBonLivraison): static
    {
        $this->numeroBonLivraison = $numeroBonLivraison;
        return $this;
    }

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): static
    {
        $this->fournisseur = $fournisseur;
        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
        return $this;
    }

    public function getDateEntree(): ?\DateTimeImmutable
    {
        return $this->dateEntree;
    }

    public function setDateEntree(?\DateTimeImmutable $dateEntree): static
    {
        $this->dateEntree = $dateEntree;
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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    /** @return Collection<int, LigneBonReception> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneBonReception $item): static
    {
        if (!$this->lignes->contains($item)) {
            $this->lignes->add($item);
            $item->setBonReception($this);
        }
        return $this;
    }

    public function clearLignes(): static
    {
        $this->lignes->clear();
        return $this;
    }

}

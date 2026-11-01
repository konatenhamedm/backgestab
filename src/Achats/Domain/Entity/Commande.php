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
#[ORM\Table(name: 'gst_commande')]
#[ORM\HasLifecycleCallbacks]
class Commande
{
    use CommonFieldsTrait;

    public const BROUILLON = 'BROUILLON';
    public const ENVOYEE = 'ENVOYEE';
    public const PARTIELLE = 'PARTIELLE';
    public const RECUE = 'RECUE';
    public const ANNULEE = 'ANNULEE';


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['commande:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Centre $centre = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['commande:read'])]
    private ?Fournisseur $fournisseur = null;

    #[ORM\Column(length: 50)]
    #[Groups(['commande:read', 'ref'])]
    private ?string $numero = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['commande:read'])]
    private ?\DateTimeImmutable $dateCommande = null;

    #[ORM\Column(length: 30, options: ['default' => 'BROUILLON'])]
    #[Groups(['commande:read', 'ref'])]
    private ?string $statut = 'BROUILLON';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['commande:read'])]
    private ?string $notes = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['commande:read'])]
    private ?\DateTimeImmutable $dateEnvoi = null;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: LigneCommande::class, cascade: ['persist','remove'], orphanRemoval: true)]
    #[Groups(['commande:read'])]
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

    public function getFournisseur(): ?Fournisseur
    {
        return $this->fournisseur;
    }

    public function setFournisseur(?Fournisseur $fournisseur): static
    {
        $this->fournisseur = $fournisseur;
        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getDateCommande(): ?\DateTimeImmutable
    {
        return $this->dateCommande;
    }

    public function setDateCommande(?\DateTimeImmutable $dateCommande): static
    {
        $this->dateCommande = $dateCommande;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDateEnvoi(): ?\DateTimeImmutable
    {
        return $this->dateEnvoi;
    }

    public function setDateEnvoi(?\DateTimeImmutable $dateEnvoi): static
    {
        $this->dateEnvoi = $dateEnvoi;
        return $this;
    }

    /** @return Collection<int, LigneCommande> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneCommande $item): static
    {
        if (!$this->lignes->contains($item)) {
            $this->lignes->add($item);
            $item->setCommande($this);
        }
        return $this;
    }

    public function clearLignes(): static
    {
        $this->lignes->clear();
        return $this;
    }

}

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
#[ORM\Table(name: 'gst_facture_fournisseur')]
#[ORM\HasLifecycleCallbacks]
class FactureFournisseur
{
    use CommonFieldsTrait;

    public const NON_PAYEE = 'NON_PAYEE';
    public const PARTIELLEMENT_PAYEE = 'PARTIELLEMENT_PAYEE';
    public const PAYEE = 'PAYEE';


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['factureFournisseur:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Centre $centre = null;

    #[ORM\Column(length: 100)]
    #[Groups(['factureFournisseur:read', 'ref'])]
    private ?string $numeroFacture = null;

    #[ORM\ManyToOne(targetEntity: Fournisseur::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['factureFournisseur:read'])]
    private ?Fournisseur $fournisseur = null;

    #[ORM\ManyToOne(targetEntity: BonReception::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['factureFournisseur:read'])]
    private ?BonReception $bonReception = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['factureFournisseur:read'])]
    private ?string $montantTotal = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['factureFournisseur:read'])]
    private ?\DateTimeImmutable $dateFacture = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['factureFournisseur:read'])]
    private ?\DateTimeImmutable $dateEcheance = null;

    #[ORM\Column(length: 30, options: ['default' => 'NON_PAYEE'])]
    #[Groups(['factureFournisseur:read'])]
    private ?string $statutPaiement = 'NON_PAYEE';

    #[ORM\OneToMany(mappedBy: 'facture', targetEntity: PaiementFacture::class, cascade: ['persist','remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['datePaiement' => 'ASC'])]
    #[Groups(['factureFournisseur:read'])]
    private Collection $paiements;

    public function __construct()
    {
        $this->paiements = new ArrayCollection();
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

    public function getNumeroFacture(): ?string
    {
        return $this->numeroFacture;
    }

    public function setNumeroFacture(?string $numeroFacture): static
    {
        $this->numeroFacture = $numeroFacture;
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

    public function getBonReception(): ?BonReception
    {
        return $this->bonReception;
    }

    public function setBonReception(?BonReception $bonReception): static
    {
        $this->bonReception = $bonReception;
        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;
        return $this;
    }

    public function getDateFacture(): ?\DateTimeImmutable
    {
        return $this->dateFacture;
    }

    public function setDateFacture(?\DateTimeImmutable $dateFacture): static
    {
        $this->dateFacture = $dateFacture;
        return $this;
    }

    public function getDateEcheance(): ?\DateTimeImmutable
    {
        return $this->dateEcheance;
    }

    public function setDateEcheance(?\DateTimeImmutable $dateEcheance): static
    {
        $this->dateEcheance = $dateEcheance;
        return $this;
    }

    public function getStatutPaiement(): ?string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(?string $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;
        return $this;
    }

    /** @return Collection<int, PaiementFacture> */
    public function getPaiements(): Collection
    {
        return $this->paiements;
    }

    public function addPaiement(PaiementFacture $item): static
    {
        if (!$this->paiements->contains($item)) {
            $this->paiements->add($item);
            $item->setFacture($this);
        }
        return $this;
    }

    public function clearPaiements(): static
    {
        $this->paiements->clear();
        return $this;
    }

    #[Groups(['factureFournisseur:read'])]
    public function getMontantPaye(): string
    {
        $total = '0.00';
        foreach ($this->paiements as $paiement) {
            $total = bcadd($total, $paiement->getMontantRegle() ?? '0', 2);
        }
        return $total;
    }

    #[Groups(['factureFournisseur:read'])]
    public function getResteAPayer(): string
    {
        return bcsub($this->montantTotal ?? '0', $this->getMontantPaye(), 2);
    }

    public function recalculerStatut(): void
    {
        $paye = $this->getMontantPaye();
        if (bccomp($paye, '0', 2) <= 0) {
            $this->statutPaiement = self::NON_PAYEE;
        } elseif (bccomp($paye, $this->montantTotal ?? '0', 2) >= 0) {
            $this->statutPaiement = self::PAYEE;
        } else {
            $this->statutPaiement = self::PARTIELLEMENT_PAYEE;
        }
    }

}

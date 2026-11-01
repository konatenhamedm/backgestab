<?php

namespace App\Achats\Domain\Entity;

use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_paiement_facture')]
#[ORM\HasLifecycleCallbacks]
class PaiementFacture
{
    use CommonFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['paiementFacture:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FactureFournisseur::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FactureFournisseur $facture = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Groups(['paiementFacture:read'])]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['paiementFacture:read'])]
    private ?string $montantRegle = null;

    #[ORM\Column(length: 30)]
    #[Groups(['paiementFacture:read'])]
    private ?string $modePaiement = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['paiementFacture:read'])]
    private ?string $reference = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFacture(): ?FactureFournisseur
    {
        return $this->facture;
    }

    public function setFacture(?FactureFournisseur $facture): static
    {
        $this->facture = $facture;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeImmutable $datePaiement): static
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getMontantRegle(): ?string
    {
        return $this->montantRegle;
    }

    public function setMontantRegle(?string $montantRegle): static
    {
        $this->montantRegle = $montantRegle;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

}

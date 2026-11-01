<?php

namespace App\Stock\Domain\Entity;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Shared\Domain\Trait\IsActiveTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_article', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_gst_article_centre_code_sku', columns: ['centre_id', 'code_sku'])])]
#[ORM\HasLifecycleCallbacks]
class Article
{
    use CommonFieldsTrait;
    use IsActiveTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['article:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Centre $centre = null;

    #[ORM\ManyToOne(targetEntity: CategorieArticle::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['article:read'])]
    private ?CategorieArticle $categorie = null;

    #[ORM\Column(length: 100)]
    #[Groups(['article:read', 'ref'])]
    private ?string $codeSku = null;

    #[ORM\Column(length: 255)]
    #[Groups(['article:read', 'ref'])]
    private ?string $designation = null;

    #[ORM\Column(length: 50, options: ['default' => 'Unité'])]
    #[Groups(['article:read', 'ref'])]
    private ?string $unite = 'Unité';

    #[ORM\Column()]
    #[Groups(['article:read', 'stock'])]
    private ?int $quantiteStock = 0;

    #[ORM\Column()]
    #[Groups(['article:read', 'stock'])]
    private ?int $seuilAlerteMin = 0;

    #[ORM\Column()]
    #[Groups(['article:read', 'stock'])]
    private ?int $seuilReapproCible = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['article:read'])]
    private ?string $prixMoyenHt = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['article:read'])]
    private ?string $description = null;

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

    public function getCategorie(): ?CategorieArticle
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieArticle $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getCodeSku(): ?string
    {
        return $this->codeSku;
    }

    public function setCodeSku(?string $codeSku): static
    {
        $this->codeSku = $codeSku;
        return $this;
    }

    public function getDesignation(): ?string
    {
        return $this->designation;
    }

    public function setDesignation(?string $designation): static
    {
        $this->designation = $designation;
        return $this;
    }

    public function getUnite(): ?string
    {
        return $this->unite;
    }

    public function setUnite(?string $unite): static
    {
        $this->unite = $unite;
        return $this;
    }

    public function getQuantiteStock(): ?int
    {
        return $this->quantiteStock;
    }

    public function setQuantiteStock(?int $quantiteStock): static
    {
        $this->quantiteStock = $quantiteStock;
        return $this;
    }

    public function getSeuilAlerteMin(): ?int
    {
        return $this->seuilAlerteMin;
    }

    public function setSeuilAlerteMin(?int $seuilAlerteMin): static
    {
        $this->seuilAlerteMin = $seuilAlerteMin;
        return $this;
    }

    public function getSeuilReapproCible(): ?int
    {
        return $this->seuilReapproCible;
    }

    public function setSeuilReapproCible(?int $seuilReapproCible): static
    {
        $this->seuilReapproCible = $seuilReapproCible;
        return $this;
    }

    public function getPrixMoyenHt(): ?string
    {
        return $this->prixMoyenHt;
    }

    public function setPrixMoyenHt(?string $prixMoyenHt): static
    {
        $this->prixMoyenHt = $prixMoyenHt;
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

    public function isSousSeuil(): bool
    {
        return $this->quantiteStock <= $this->seuilAlerteMin;
    }

    public function getBesoin(): int
    {
        return max(0, $this->seuilReapproCible - $this->quantiteStock);
    }

}

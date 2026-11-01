<?php

namespace App\Stock\Domain\Entity;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Shared\Domain\Trait\CommonFieldsTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_mouvement_stock')]
#[ORM\HasLifecycleCallbacks]
class MouvementStock
{
    use CommonFieldsTrait;

    public const ENTREE_ACHAT = 'ENTREE_ACHAT';
    public const SORTIE_DEMANDE = 'SORTIE_DEMANDE';
    public const AJUSTEMENT = 'AJUSTEMENT';


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['mouvementStock:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Entreprise::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Entreprise $entreprise = null;

    #[ORM\ManyToOne(targetEntity: Centre::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Centre $centre = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['mouvementStock:read'])]
    private ?Article $article = null;

    #[ORM\Column(length: 30)]
    #[Groups(['mouvementStock:read'])]
    private ?string $type = null;

    #[ORM\Column()]
    #[Groups(['mouvementStock:read'])]
    private ?int $quantite = null;

    #[ORM\Column()]
    #[Groups(['mouvementStock:read'])]
    private ?int $stockApres = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['mouvementStock:read'])]
    private ?\DateTimeImmutable $dateMouvement = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['mouvementStock:read'])]
    private ?string $referenceDocument = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['mouvementStock:read'])]
    private ?string $commentaire = null;

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

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getStockApres(): ?int
    {
        return $this->stockApres;
    }

    public function setStockApres(?int $stockApres): static
    {
        $this->stockApres = $stockApres;
        return $this;
    }

    public function getDateMouvement(): ?\DateTimeImmutable
    {
        return $this->dateMouvement;
    }

    public function setDateMouvement(?\DateTimeImmutable $dateMouvement): static
    {
        $this->dateMouvement = $dateMouvement;
        return $this;
    }

    public function getReferenceDocument(): ?string
    {
        return $this->referenceDocument;
    }

    public function setReferenceDocument(?string $referenceDocument): static
    {
        $this->referenceDocument = $referenceDocument;
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

}

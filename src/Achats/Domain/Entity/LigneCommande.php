<?php

namespace App\Achats\Domain\Entity;

use App\Stock\Domain\Entity\Article;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_ligne_commande')]
class LigneCommande
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ligneCommande:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ligneCommande:read'])]
    private ?Article $article = null;

    #[ORM\Column()]
    #[Groups(['ligneCommande:read'])]
    private ?int $quantite = null;

    #[ORM\Column()]
    #[Groups(['ligneCommande:read'])]
    private ?int $quantiteRecue = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['ligneCommande:read'])]
    private ?string $prixUnitaireHt = '0.00';

    public function getId(): ?int
    {
        return $this->id;
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

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
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

    public function getQuantiteRecue(): ?int
    {
        return $this->quantiteRecue;
    }

    public function setQuantiteRecue(?int $quantiteRecue): static
    {
        $this->quantiteRecue = $quantiteRecue;
        return $this;
    }

    public function getPrixUnitaireHt(): ?string
    {
        return $this->prixUnitaireHt;
    }

    public function setPrixUnitaireHt(?string $prixUnitaireHt): static
    {
        $this->prixUnitaireHt = $prixUnitaireHt;
        return $this;
    }

}

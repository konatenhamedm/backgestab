<?php

namespace App\Achats\Domain\Entity;

use App\Stock\Domain\Entity\Article;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_ligne_bon_reception')]
class LigneBonReception
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ligneBonReception:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BonReception::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BonReception $bonReception = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ligneBonReception:read'])]
    private ?Article $article = null;

    #[ORM\Column()]
    #[Groups(['ligneBonReception:read'])]
    private ?int $quantiteRecue = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 2)]
    #[Groups(['ligneBonReception:read'])]
    private ?string $prixUnitaireHt = '0.00';

    public function getId(): ?int
    {
        return $this->id;
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

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
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

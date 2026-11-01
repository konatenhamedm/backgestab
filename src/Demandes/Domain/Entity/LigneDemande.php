<?php

namespace App\Demandes\Domain\Entity;

use App\Stock\Domain\Entity\Article;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_ligne_demande')]
class LigneDemande
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ligneDemande:read', 'ref'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DemandeMateriel::class, inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DemandeMateriel $demande = null;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ligneDemande:read'])]
    private ?Article $article = null;

    #[ORM\Column()]
    #[Groups(['ligneDemande:read'])]
    private ?int $quantiteDemandee = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['ligneDemande:read'])]
    private ?int $quantiteAccordee = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function setArticle(?Article $article): static
    {
        $this->article = $article;
        return $this;
    }

    public function getQuantiteDemandee(): ?int
    {
        return $this->quantiteDemandee;
    }

    public function setQuantiteDemandee(?int $quantiteDemandee): static
    {
        $this->quantiteDemandee = $quantiteDemandee;
        return $this;
    }

    public function getQuantiteAccordee(): ?int
    {
        return $this->quantiteAccordee;
    }

    public function setQuantiteAccordee(?int $quantiteAccordee): static
    {
        $this->quantiteAccordee = $quantiteAccordee;
        return $this;
    }

}

<?php

namespace App\Stock\Application\Service;

use App\Stock\Domain\Entity\Article;
use App\Stock\Domain\Entity\MouvementStock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point unique de modification du stock : toute variation crée un mouvement tracé.
 * N'appelle pas flush() : l'appelant décide de la transaction.
 */
class StockService
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @param int $delta positif pour une entrée, négatif pour une sortie
     */
    public function appliquer(Article $article, int $delta, string $type, ?string $reference = null, ?string $commentaire = null, ?string $prixUnitaireHt = null): MouvementStock
    {
        if ($delta === 0) {
            throw new \DomainException('La quantité du mouvement ne peut pas être nulle.');
        }

        $apres = $article->getQuantiteStock() + $delta;
        if ($apres < 0) {
            throw new \DomainException(sprintf(
                'Stock insuffisant pour « %s » (disponible : %d, demandé : %d).',
                $article->getDesignation(), $article->getQuantiteStock(), abs($delta)
            ));
        }

        // Prix moyen pondéré sur les entrées valorisées
        if ($delta > 0 && $prixUnitaireHt !== null && bccomp($prixUnitaireHt, '0', 2) > 0) {
            $valeurAvant = bcmul((string) $article->getQuantiteStock(), $article->getPrixMoyenHt() ?? '0', 4);
            $valeurEntree = bcmul((string) $delta, $prixUnitaireHt, 4);
            $article->setPrixMoyenHt(bcdiv(bcadd($valeurAvant, $valeurEntree, 4), (string) $apres, 2));
        }

        $article->setQuantiteStock($apres);

        $mouvement = (new MouvementStock())
            ->setEntreprise($article->getEntreprise())
            ->setCentre($article->getCentre())
            ->setArticle($article)
            ->setType($type)
            ->setQuantite($delta)
            ->setStockApres($apres)
            ->setDateMouvement(new \DateTimeImmutable())
            ->setReferenceDocument($reference)
            ->setCommentaire($commentaire);
        $this->em->persist($mouvement);

        return $mouvement;
    }
}

<?php

namespace App\Achats\Application\UseCase;

use App\Achats\Domain\Entity\BonReception;
use App\Achats\Domain\Entity\Commande;
use App\Achats\Domain\Entity\Fournisseur;
use App\Achats\Domain\Entity\LigneBonReception;
use App\Organisation\Domain\Entity\Centre;
use App\Shared\Application\Service\NumeroGenerator;
use App\Stock\Application\Service\StockService;
use App\Stock\Domain\Entity\MouvementStock;
use App\Stock\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre un bon de réception : incrémente immédiatement le stock (mouvement ENTREE_ACHAT)
 * et fait avancer la commande fournisseur liée.
 */
class CreateBonReceptionUseCase
{
    public function __construct(
        private EntityManagerInterface $em,
        private ArticleRepositoryInterface $articles,
        private StockService $stock,
        private NumeroGenerator $numeros
    ) {}

    public function execute(Centre $centre, array $data): BonReception
    {
        $entreprise = $centre->getEntreprise();
        $lignes = (array) ($data['lignes'] ?? []);
        if (!$lignes) {
            throw new \InvalidArgumentException('Ajoutez au moins une ligne au bon de réception.');
        }

        $fournisseur = $this->em->getRepository(Fournisseur::class)->findOneBy(['id' => (int) ($data['fournisseur'] ?? 0), 'entreprise' => $entreprise]);
        if (!$fournisseur) {
            throw new \InvalidArgumentException('Fournisseur introuvable.');
        }

        $commande = null;
        if (!empty($data['commande'])) {
            $commande = $this->em->getRepository(Commande::class)->findOneBy(['id' => (int) $data['commande'], 'centre' => $centre]);
            if (!$commande) {
                throw new \InvalidArgumentException('Commande introuvable.');
            }
            if (in_array($commande->getStatut(), [Commande::ANNULEE, Commande::RECUE], true)) {
                throw new \InvalidArgumentException('Cette commande est déjà clôturée.');
            }
        }

        return $this->em->wrapInTransaction(function () use ($centre, $entreprise, $data, $lignes, $fournisseur, $commande) {
            $bon = (new BonReception())
                ->setEntreprise($entreprise)
                ->setCentre($centre)
                ->setNumeroBon($this->numeros->next('BR', BonReception::class, 'numeroBon', $entreprise->getId()))
                ->setNumeroBonLivraison($data['numeroBonLivraison'] ?? null)
                ->setFournisseur($fournisseur)
                ->setCommande($commande)
                ->setDateEntree(new \DateTimeImmutable($data['dateEntree'] ?? 'today'))
                ->setCommentaire($data['commentaire'] ?? null);

            foreach ($lignes as $row) {
                $article = $this->articles->findOneInCentre((int) ($row['article'] ?? 0), $centre);
                $qte = (int) ($row['quantiteRecue'] ?? 0);
                if (!$article || $qte <= 0) {
                    throw new \InvalidArgumentException('Chaque ligne doit référencer un article du centre avec une quantité positive.');
                }
                $prix = number_format((float) ($row['prixUnitaireHt'] ?? 0), 2, '.', '');

                $ligne = (new LigneBonReception())->setArticle($article)->setQuantiteRecue($qte)->setPrixUnitaireHt($prix);
                $bon->addLigne($ligne);

                $this->stock->appliquer($article, $qte, MouvementStock::ENTREE_ACHAT, $bon->getNumeroBon(), 'Réception ' . $fournisseur->getRaisonSociale(), $prix);

                if ($commande) {
                    foreach ($commande->getLignes() as $lc) {
                        if ($lc->getArticle()->getId() === $article->getId()) {
                            $lc->setQuantiteRecue($lc->getQuantiteRecue() + $qte);
                        }
                    }
                }
            }

            if ($commande) {
                $complete = true;
                foreach ($commande->getLignes() as $lc) {
                    $complete = $complete && $lc->getQuantiteRecue() >= $lc->getQuantite();
                }
                $commande->setStatut($complete ? Commande::RECUE : Commande::PARTIELLE);
            }

            $this->em->persist($bon);
            $this->em->flush();

            return $bon;
        });
    }
}

<?php

namespace App\Demandes\Application\Service;

use App\Demandes\Domain\Entity\BonSortie;
use App\Demandes\Domain\Entity\DemandeMateriel;
use App\Demandes\Domain\Entity\LigneDemande;
use App\Demandes\Domain\Repository\DemandeMaterielRepositoryInterface;
use App\Organisation\Domain\Entity\Centre;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\NumeroGenerator;
use App\Stock\Application\Service\StockService;
use App\Stock\Domain\Entity\MouvementStock;
use App\Stock\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Workflow de dotation de matériel :
 *   EN_ATTENTE → (valider) VALIDEE → (livrer) EN_LIVRAISON → (accusé de réception) LIVREE
 *   EN_ATTENTE → (refuser) REFUSEE
 * Le stock n'est décrémenté qu'à l'accusé de réception.
 */
class DemandeWorkflow
{
    public function __construct(
        private EntityManagerInterface $em,
        private DemandeMaterielRepositoryInterface $demandes,
        private ArticleRepositoryInterface $articles,
        private StockService $stock,
        private NumeroGenerator $numeros
    ) {}

    public function soumettre(Utilisateur $demandeur, Centre $centre, array $data): DemandeMateriel
    {
        $rows = (array) ($data['lignes'] ?? []);
        if (!$rows) {
            throw new \InvalidArgumentException('Sélectionnez au moins une fourniture.');
        }

        $entreprise = $centre->getEntreprise();
        $demande = (new DemandeMateriel())
            ->setEntreprise($entreprise)
            ->setCentre($centre)
            ->setNumeroDemande($this->numeros->next('DEM', DemandeMateriel::class, 'numeroDemande', $entreprise->getId()))
            ->setDemandeur($demandeur)
            ->setService($demandeur->getService())
            ->setDateDemande(new \DateTimeImmutable())
            ->setMotif($data['motif'] ?? null);

        $vus = [];
        foreach ($rows as $row) {
            $article = $this->articles->findOneInCentre((int) ($row['article'] ?? 0), $centre);
            $qte = (int) ($row['quantiteDemandee'] ?? 0);
            if (!$article || $qte <= 0) {
                throw new \InvalidArgumentException('Chaque ligne doit référencer une fourniture du centre avec une quantité positive.');
            }
            if (isset($vus[$article->getId()])) {
                throw new \InvalidArgumentException(sprintf('« %s » est présent plusieurs fois dans la demande.', $article->getDesignation()));
            }
            $vus[$article->getId()] = true;
            $demande->addLigne((new LigneDemande())->setArticle($article)->setQuantiteDemandee($qte));
        }

        $this->demandes->add($demande, true);

        return $demande;
    }

    /** Le gestionnaire valide, ajuste à la baisse ou substitue les quantités accordées. */
    public function valider(DemandeMateriel $demande, Utilisateur $valideur, array $data): DemandeMateriel
    {
        $this->exigerStatut($demande, DemandeMateriel::EN_ATTENTE, 'validée');

        $accordees = [];
        foreach ((array) ($data['lignes'] ?? []) as $row) {
            $accordees[(int) ($row['id'] ?? 0)] = (int) ($row['quantiteAccordee'] ?? 0);
        }

        $total = 0;
        foreach ($demande->getLignes() as $ligne) {
            // Sans précision, on accorde la quantité demandée
            $qte = $accordees[$ligne->getId()] ?? $ligne->getQuantiteDemandee();
            if ($qte < 0) {
                throw new \InvalidArgumentException('Une quantité accordée ne peut pas être négative.');
            }
            $dispo = $ligne->getArticle()->getQuantiteStock();
            if ($qte > $dispo) {
                throw new \DomainException(sprintf('Stock insuffisant pour « %s » (disponible : %d, accordé : %d).', $ligne->getArticle()->getDesignation(), $dispo, $qte));
            }
            $ligne->setQuantiteAccordee($qte);
            $total += $qte;
        }
        if ($total === 0) {
            throw new \DomainException('Aucune quantité accordée : refusez plutôt la demande.');
        }

        $demande->setStatut(DemandeMateriel::VALIDEE)->setValideur($valideur)->setDateValidation(new \DateTimeImmutable());
        $this->em->flush();

        return $demande;
    }

    public function refuser(DemandeMateriel $demande, Utilisateur $valideur, ?string $motif): DemandeMateriel
    {
        $this->exigerStatut($demande, DemandeMateriel::EN_ATTENTE, 'refusée');
        if (empty(trim((string) $motif))) {
            throw new \InvalidArgumentException('Le motif du refus est obligatoire.');
        }

        $demande->setStatut(DemandeMateriel::REFUSEE)->setMotifRefus($motif)->setValideur($valideur)->setDateValidation(new \DateTimeImmutable());
        $this->em->flush();

        return $demande;
    }

    /** Préparation & livraison : émission du bon de sortie. */
    public function livrer(DemandeMateriel $demande, Utilisateur $livreur): BonSortie
    {
        $this->exigerStatut($demande, DemandeMateriel::VALIDEE, 'livrée');

        foreach ($demande->getLignes() as $ligne) {
            $qte = $ligne->getQuantiteAccordee() ?? 0;
            if ($qte > $ligne->getArticle()->getQuantiteStock()) {
                throw new \DomainException(sprintf('Stock insuffisant pour « %s » : réajustez la demande.', $ligne->getArticle()->getDesignation()));
            }
        }

        $entreprise = $demande->getEntreprise();
        $bon = (new BonSortie())
            ->setEntreprise($entreprise)
            ->setCentre($demande->getCentre())
            ->setDemande($demande)
            ->setNumeroBon($this->numeros->next('BS', BonSortie::class, 'numeroBon', $entreprise->getId()))
            ->setDateLivraison(new \DateTimeImmutable())
            ->setLivrePar($livreur);
        $demande->addBonsSortie($bon);
        $demande->setStatut(DemandeMateriel::EN_LIVRAISON);

        $this->em->persist($bon);
        $this->em->flush();

        return $bon;
    }

    /** Accusé de réception : décrémentation effective du stock. */
    public function accuserReception(DemandeMateriel $demande, Utilisateur $acteur, ?string $confirmation): DemandeMateriel
    {
        $this->exigerStatut($demande, DemandeMateriel::EN_LIVRAISON, 'réceptionnée');

        $this->em->wrapInTransaction(function () use ($demande, $confirmation) {
            $bon = $demande->getBonsSortie()->last();

            foreach ($demande->getLignes() as $ligne) {
                $qte = $ligne->getQuantiteAccordee() ?? 0;
                if ($qte > 0) {
                    $this->stock->appliquer($ligne->getArticle(), -$qte, MouvementStock::SORTIE_DEMANDE, $demande->getNumeroDemande(), 'Sortie ' . $bon->getNumeroBon());
                }
            }

            $bon->setDateAccuseReception(new \DateTimeImmutable())->setConfirmation($confirmation ?: 'Réception confirmée');
            $demande->setStatut(DemandeMateriel::LIVREE);
            $this->em->flush();
        });

        return $demande;
    }

    public function annuler(DemandeMateriel $demande): void
    {
        $this->exigerStatut($demande, DemandeMateriel::EN_ATTENTE, 'annulée');
        $this->em->remove($demande);
        $this->em->flush();
    }

    private function exigerStatut(DemandeMateriel $demande, string $attendu, string $action): void
    {
        if ($demande->getStatut() !== $attendu) {
            throw new \DomainException(sprintf('Cette demande ne peut pas être %s (statut actuel : %s).', $action, $demande->getStatut()));
        }
    }
}

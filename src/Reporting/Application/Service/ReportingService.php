<?php

namespace App\Reporting\Application\Service;

use App\Achats\Domain\Entity\Commande;
use App\Achats\Domain\Entity\FactureFournisseur;
use App\Achats\Domain\Repository\FactureFournisseurRepositoryInterface;
use App\Demandes\Domain\Entity\DemandeMateriel;
use App\Demandes\Domain\Entity\LigneDemande;
use App\Demandes\Domain\Repository\DemandeMaterielRepositoryInterface;
use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Stock\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * États, rapports et indicateurs du tableau de bord.
 */
class ReportingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ArticleRepositoryInterface $articles,
        private DemandeMaterielRepositoryInterface $demandes,
        private FactureFournisseurRepositoryInterface $factures
    ) {}

    public function dashboard(Entreprise $entreprise, Centre $centre): array
    {
        $debutAnnee = new \DateTimeImmutable('first day of january this year');

        $commandesEnCours = (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')->from(Commande::class, 'c')
            ->where('c.centre = :centre')->andWhere('c.statut IN (:st)')
            ->setParameter('centre', $centre)
            ->setParameter('st', [Commande::BROUILLON, Commande::ENVOYEE, Commande::PARTIELLE])
            ->getQuery()->getSingleScalarResult();

        $budgetConsomme = $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(f.montantTotal), 0)')->from(FactureFournisseur::class, 'f')
            ->where('f.entreprise = :e')->andWhere('f.dateFacture >= :d')
            ->setParameter('e', $entreprise)->setParameter('d', $debutAnnee)
            ->getQuery()->getSingleScalarResult();

        $alertes = $this->articles->findSousSeuil($centre);
        $enAttente = $this->factures->findEnAttente($entreprise);
        $resteDu = '0.00';
        foreach ($enAttente as $f) {
            $resteDu = bcadd($resteDu, $f->getResteAPayer(), 2);
        }

        return [
            'articles' => $this->articles->countActifs($centre),
            'commandesEnCours' => $commandesEnCours,
            'alertesSeuil' => count($alertes),
            'budgetConsomme' => number_format((float) $budgetConsomme, 2, '.', ''),
            'demandesEnAttente' => $this->demandes->countByStatut($centre, DemandeMateriel::EN_ATTENTE),
            'demandesALivrer' => $this->demandes->countByStatut($centre, DemandeMateriel::VALIDEE),
            'facturesEnAttente' => count($enAttente),
            'resteAPayer' => $resteDu,
            'devise' => $entreprise->getDevise(),
            'articlesEnAlerte' => array_map(fn ($a) => [
                'id' => $a->getId(), 'codeSku' => $a->getCodeSku(), 'designation' => $a->getDesignation(),
                'stock' => $a->getQuantiteStock(), 'seuil' => $a->getSeuilAlerteMin(), 'statut' => $a->getQuantiteStock() <= 0 ? 'rupture' : 'alerte',
            ], array_slice($alertes, 0, 8)),
        ];
    }

    /** Besoin = Seuil cible − Stock actuel, dès que Stock actuel ≤ Seuil d'alerte. */
    public function reapprovisionnement(Centre $centre): array
    {
        $lignes = [];
        foreach ($this->articles->findSousSeuil($centre) as $a) {
            $besoin = $a->getBesoin();
            if ($besoin <= 0) {
                continue;
            }
            $lignes[] = [
                'article' => $a->getId(),
                'codeSku' => $a->getCodeSku(),
                'designation' => $a->getDesignation(),
                'unite' => $a->getUnite(),
                'stockActuel' => $a->getQuantiteStock(),
                'seuilAlerteMin' => $a->getSeuilAlerteMin(),
                'seuilReapproCible' => $a->getSeuilReapproCible(),
                'quantiteACommander' => $besoin,
                'prixMoyenHt' => $a->getPrixMoyenHt(),
                'montantEstimeHt' => bcmul((string) $besoin, $a->getPrixMoyenHt() ?? '0', 2),
            ];
        }

        $total = array_reduce($lignes, fn ($c, $l) => bcadd($c, $l['montantEstimeHt'], 2), '0.00');

        return ['lignes' => $lignes, 'montantTotalEstimeHt' => $total];
    }

    /** Analyse de consommation sur les demandes livrées de la période. */
    public function consommation(Centre $centre, \DateTimeImmutable $du, \DateTimeImmutable $au, int $limit = 10): array
    {
        $base = fn () => $this->em->createQueryBuilder()
            ->from(LigneDemande::class, 'l')
            ->join('l.demande', 'd')->join('l.article', 'a')->join('d.demandeur', 'u')->leftJoin('d.service', 's')
            ->where('d.centre = :c')->andWhere('d.statut = :st')->andWhere('d.dateDemande BETWEEN :du AND :au')
            ->setParameter('c', $centre)->setParameter('st', DemandeMateriel::LIVREE)
            ->setParameter('du', $du->setTime(0, 0))->setParameter('au', $au->setTime(23, 59, 59));

        $topArticles = $base()
            ->select('a.id AS id, a.codeSku AS codeSku, a.designation AS designation, a.unite AS unite, SUM(l.quantiteAccordee) AS quantite')
            ->groupBy('a.id, a.codeSku, a.designation, a.unite')->orderBy('quantite', 'DESC')->setMaxResults($limit)
            ->getQuery()->getArrayResult();

        $topDemandeurs = $base()
            ->select("u.id AS id, CONCAT(u.prenom, ' ', u.nom) AS nom, SUM(l.quantiteAccordee) AS quantite, COUNT(DISTINCT d.id) AS demandes")
            ->groupBy('u.id, u.prenom, u.nom')->orderBy('quantite', 'DESC')->setMaxResults($limit)
            ->getQuery()->getArrayResult();

        $topServices = $base()
            ->select("COALESCE(s.nom, 'Sans service') AS nom, SUM(l.quantiteAccordee) AS quantite, COUNT(DISTINCT d.id) AS demandes")
            ->groupBy('s.id, s.nom')->orderBy('quantite', 'DESC')->setMaxResults($limit)
            ->getQuery()->getArrayResult();

        // Écarts demandé / accordé : sur toutes les demandes traitées (validées, en livraison, livrées)
        $ecarts = $this->em->createQueryBuilder()
            ->select('a.id AS id, a.designation AS designation, SUM(l.quantiteDemandee) AS demande, SUM(l.quantiteAccordee) AS accorde')
            ->from(LigneDemande::class, 'l')->join('l.demande', 'd')->join('l.article', 'a')
            ->where('d.centre = :c')->andWhere('d.statut IN (:sts)')->andWhere('d.dateDemande BETWEEN :du AND :au')
            ->setParameter('c', $centre)
            ->setParameter('sts', [DemandeMateriel::VALIDEE, DemandeMateriel::EN_LIVRAISON, DemandeMateriel::LIVREE])
            ->setParameter('du', $du->setTime(0, 0))->setParameter('au', $au->setTime(23, 59, 59))
            ->groupBy('a.id, a.designation')
            ->having('SUM(l.quantiteDemandee) <> SUM(l.quantiteAccordee)')
            ->orderBy('a.designation', 'ASC')
            ->getQuery()->getArrayResult();

        $cast = fn (array $rows, array $fields) => array_map(function ($r) use ($fields) {
            foreach ($fields as $f) {
                $r[$f] = (int) $r[$f];
            }
            return $r;
        }, $rows);

        return [
            'periode' => ['du' => $du->format('Y-m-d'), 'au' => $au->format('Y-m-d')],
            'topArticles' => $cast($topArticles, ['quantite']),
            'topDemandeurs' => $cast($topDemandeurs, ['quantite', 'demandes']),
            'topServices' => $cast($topServices, ['quantite', 'demandes']),
            'ecarts' => array_map(fn ($r) => $r + ['ecart' => (int) $r['accorde'] - (int) $r['demande']], $cast($ecarts, ['demande', 'accorde'])),
        ];
    }

    /** Factures en attente de paiement / échéancier fournisseur. */
    public function echeancier(Entreprise $entreprise): array
    {
        $today = new \DateTimeImmutable('today');
        $lignes = [];
        $parFournisseur = [];
        $totalDu = '0.00';
        $totalRetard = '0.00';

        foreach ($this->factures->findEnAttente($entreprise) as $f) {
            $reste = $f->getResteAPayer();
            $echeance = $f->getDateEcheance();
            $enRetard = $echeance !== null && $echeance < $today;

            $lignes[] = [
                'id' => $f->getId(),
                'numeroFacture' => $f->getNumeroFacture(),
                'fournisseur' => $f->getFournisseur()->getRaisonSociale(),
                'dateFacture' => $f->getDateFacture()->format('Y-m-d'),
                'dateEcheance' => $echeance?->format('Y-m-d'),
                'montantTotal' => $f->getMontantTotal(),
                'montantPaye' => $f->getMontantPaye(),
                'resteAPayer' => $reste,
                'statutPaiement' => $f->getStatutPaiement(),
                'enRetard' => $enRetard,
                'joursRetard' => $enRetard ? (int) $echeance->diff($today)->days : 0,
            ];

            $nom = $f->getFournisseur()->getRaisonSociale();
            $parFournisseur[$nom] = bcadd($parFournisseur[$nom] ?? '0.00', $reste, 2);
            $totalDu = bcadd($totalDu, $reste, 2);
            if ($enRetard) {
                $totalRetard = bcadd($totalRetard, $reste, 2);
            }
        }

        arsort($parFournisseur);

        return [
            'lignes' => $lignes,
            'totalAPayer' => $totalDu,
            'totalEnRetard' => $totalRetard,
            'parFournisseur' => array_map(fn ($nom, $montant) => ['fournisseur' => $nom, 'resteAPayer' => $montant], array_keys($parFournisseur), $parFournisseur),
        ];
    }
}

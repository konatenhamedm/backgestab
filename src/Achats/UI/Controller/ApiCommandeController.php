<?php

namespace App\Achats\UI\Controller;

use App\Achats\Domain\Entity\Commande;
use App\Achats\Domain\Entity\Fournisseur;
use App\Achats\Domain\Entity\LigneCommande;
use App\Shared\Application\Service\NumeroGenerator;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use App\Stock\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/achats/commandes')]
class ApiCommandeController extends ApiInterface
{
    private const GROUPS = ['commande:read', 'ligneCommande:read', 'fournisseur:read', 'article:read', 'ref'];

    #[RequirePermission('commandes', 'R', or: [['receptions', 'C']])]
    #[Route('', name: 'api_achats_commandes_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('c', 'f', 'l', 'a')->from(Commande::class, 'c')
            ->join('c.fournisseur', 'f')->leftJoin('c.lignes', 'l')->leftJoin('l.article', 'a')
            ->where('c.centre = :centre')->setParameter('centre', $tenant->requireCentre())
            ->orderBy('c.dateCommande', 'DESC')->addOrderBy('c.id', 'DESC');

        if ($statut = $request->query->get('statut')) {
            $qb->andWhere('c.statut = :s')->setParameter('s', $statut);
        }
        if ($request->query->get('enCours')) {
            $qb->andWhere('c.statut IN (:enCours)')->setParameter('enCours', [Commande::BROUILLON, Commande::ENVOYEE, Commande::PARTIELLE]);
        }

        return $this->responseData($qb->getQuery()->getResult(), [], [], self::GROUPS);
    }

    #[RequirePermission('commandes', 'R', or: [['receptions', 'C']])]
    #[Route('/{id}', name: 'api_achats_commandes_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $commande = $this->find($id, $tenant, $em);
        return $commande ? $this->responseData($commande, [], [], self::GROUPS) : $this->errorResponse(null, 'Commande introuvable.', 404);
    }

    #[RequirePermission('commandes', 'C')]
    #[Route('', name: 'api_achats_commandes_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em, ArticleRepositoryInterface $articles, NumeroGenerator $numeros): JsonResponse
    {
        $data = $this->body($request);
        $centre = $tenant->requireCentre();
        $entreprise = $tenant->requireEntreprise();

        $fournisseur = $em->getRepository(Fournisseur::class)->findOneBy(['id' => (int) ($data['fournisseur'] ?? 0), 'entreprise' => $entreprise]);
        if (!$fournisseur) {
            return $this->errorResponse(null, 'Fournisseur introuvable.', 404);
        }

        $commande = (new Commande())
            ->setEntreprise($entreprise)->setCentre($centre)->setFournisseur($fournisseur)
            ->setNumero($numeros->next('CMD', Commande::class, 'numero', $entreprise->getId()))
            ->setDateCommande(new \DateTimeImmutable($data['dateCommande'] ?? 'today'))
            ->setNotes($data['notes'] ?? null);

        try {
            $this->fillLignes($commande, (array) ($data['lignes'] ?? []), $articles, $centre);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        $em->persist($commande);
        $em->flush();

        return $this->setStatusCode(201)->responseData($commande, [], [], self::GROUPS);
    }

    /** « Réapprovisionnez-vous en un clic » : une commande brouillon à partir des articles sous le seuil. */
    #[RequirePermission('commandes', 'C')]
    #[Route('/depuis-reapprovisionnement', name: 'api_achats_commandes_reappro', methods: ['POST'])]
    public function depuisReappro(Request $request, TenantContext $tenant, EntityManagerInterface $em, ArticleRepositoryInterface $articles, NumeroGenerator $numeros): JsonResponse
    {
        $data = $this->body($request);
        $centre = $tenant->requireCentre();
        $entreprise = $tenant->requireEntreprise();

        $fournisseur = $em->getRepository(Fournisseur::class)->findOneBy(['id' => (int) ($data['fournisseur'] ?? 0), 'entreprise' => $entreprise]);
        if (!$fournisseur) {
            return $this->errorResponse(null, 'Choisissez un fournisseur.', 400);
        }

        $ids = array_map('intval', (array) ($data['articles'] ?? []));
        $lignes = [];
        foreach ($articles->findSousSeuil($centre) as $article) {
            if (($ids && !in_array($article->getId(), $ids, true)) || $article->getBesoin() <= 0) {
                continue;
            }
            $lignes[] = ['article' => $article->getId(), 'quantite' => $article->getBesoin(), 'prixUnitaireHt' => $article->getPrixMoyenHt()];
        }
        if (!$lignes) {
            return $this->errorResponse(null, 'Aucun article à réapprovisionner.', 400);
        }

        $commande = (new Commande())
            ->setEntreprise($entreprise)->setCentre($centre)->setFournisseur($fournisseur)
            ->setNumero($numeros->next('CMD', Commande::class, 'numero', $entreprise->getId()))
            ->setDateCommande(new \DateTimeImmutable('today'))
            ->setNotes('Commande générée automatiquement (réapprovisionnement).');
        $this->fillLignes($commande, $lignes, $articles, $centre);

        $em->persist($commande);
        $em->flush();

        return $this->setStatusCode(201)->responseData($commande, [], [], self::GROUPS);
    }

    #[RequirePermission('commandes', 'U')]
    #[Route('/{id}', name: 'api_achats_commandes_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em, ArticleRepositoryInterface $articles): JsonResponse
    {
        $commande = $this->find($id, $tenant, $em);
        if (!$commande) {
            return $this->errorResponse(null, 'Commande introuvable.', 404);
        }
        if ($commande->getStatut() !== Commande::BROUILLON) {
            return $this->errorResponse(null, 'Seule une commande en brouillon peut être modifiée.', 400);
        }

        $data = $this->body($request);
        if (array_key_exists('notes', $data)) $commande->setNotes($data['notes']);
        if (isset($data['lignes'])) {
            try {
                $commande->clearLignes();
                $em->flush();
                $this->fillLignes($commande, (array) $data['lignes'], $articles, $tenant->requireCentre());
            } catch (\InvalidArgumentException $e) {
                return $this->errorResponse(null, $e->getMessage(), 400);
            }
        }
        $em->flush();

        return $this->responseData($commande, [], [], self::GROUPS);
    }

    #[RequirePermission(action: 'commandes.envoyer')]
    #[Route('/{id}/envoyer', name: 'api_achats_commandes_send', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function envoyer(int $id, TenantContext $tenant, EntityManagerInterface $em, MailerInterface $mailer): JsonResponse
    {
        $commande = $this->find($id, $tenant, $em);
        if (!$commande) {
            return $this->errorResponse(null, 'Commande introuvable.', 404);
        }
        if ($commande->getStatut() !== Commande::BROUILLON) {
            return $this->errorResponse(null, 'Cette commande a déjà été envoyée.', 400);
        }
        if ($commande->getLignes()->isEmpty()) {
            return $this->errorResponse(null, 'La commande ne contient aucune ligne.', 400);
        }

        $commande->setStatut(Commande::ENVOYEE)->setDateEnvoi(new \DateTimeImmutable());
        $em->flush();

        $emailEnvoye = false;
        $fournisseur = $commande->getFournisseur();
        if ($fournisseur->getEmail()) {
            $corps = sprintf("Bonjour,\n\nVeuillez trouver notre commande %s :\n\n", $commande->getNumero());
            foreach ($commande->getLignes() as $l) {
                $corps .= sprintf("- %s (%s) : %d %s\n", $l->getArticle()->getDesignation(), $l->getArticle()->getCodeSku(), $l->getQuantite(), $l->getArticle()->getUnite());
            }
            try {
                $mailer->send((new Email())
                    ->from('no-reply@gestad.app')
                    ->to($fournisseur->getEmail())
                    ->subject('Commande ' . $commande->getNumero() . ' - ' . $commande->getEntreprise()->getNom())
                    ->text($corps . "\nCordialement,\n" . $commande->getEntreprise()->getNom()));
                $emailEnvoye = true;
            } catch (\Throwable) {
                // L'envoi d'email ne doit pas bloquer le workflow
            }
        }

        return $this->setMessage($emailEnvoye ? 'Commande envoyée au fournisseur.' : 'Commande marquée comme envoyée.')
            ->responseData($commande, [], ['emailEnvoye' => $emailEnvoye], self::GROUPS);
    }

    #[RequirePermission('commandes', 'D')]
    #[Route('/{id}/annuler', name: 'api_achats_commandes_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annuler(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $commande = $this->find($id, $tenant, $em);
        if (!$commande) {
            return $this->errorResponse(null, 'Commande introuvable.', 404);
        }
        if (in_array($commande->getStatut(), [Commande::RECUE, Commande::PARTIELLE], true)) {
            return $this->errorResponse(null, 'Une commande déjà (partiellement) reçue ne peut pas être annulée.', 400);
        }
        $commande->setStatut(Commande::ANNULEE);
        $em->flush();

        return $this->responseData($commande, [], [], self::GROUPS);
    }

    private function find(int $id, TenantContext $tenant, EntityManagerInterface $em): ?Commande
    {
        return $em->getRepository(Commande::class)->findOneBy(['id' => $id, 'centre' => $tenant->requireCentre()]);
    }

    private function fillLignes(Commande $commande, array $rows, ArticleRepositoryInterface $articles, $centre): void
    {
        if (!$rows) {
            return;
        }
        foreach ($rows as $row) {
            $article = $articles->findOneInCentre((int) ($row['article'] ?? 0), $centre);
            $qte = (int) ($row['quantite'] ?? 0);
            if (!$article || $qte <= 0) {
                throw new \InvalidArgumentException('Chaque ligne doit référencer un article du centre avec une quantité positive.');
            }
            $commande->addLigne((new LigneCommande())
                ->setArticle($article)
                ->setQuantite($qte)
                ->setPrixUnitaireHt(number_format((float) ($row['prixUnitaireHt'] ?? $article->getPrixMoyenHt() ?? 0), 2, '.', '')));
        }
    }
}

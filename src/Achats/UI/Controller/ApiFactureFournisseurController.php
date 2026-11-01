<?php

namespace App\Achats\UI\Controller;

use App\Achats\Application\UseCase\EnregistrerPaiementUseCase;
use App\Achats\Domain\Entity\BonReception;
use App\Achats\Domain\Entity\FactureFournisseur;
use App\Achats\Domain\Entity\Fournisseur;
use App\Achats\Domain\Repository\FactureFournisseurRepositoryInterface;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/achats/factures')]
class ApiFactureFournisseurController extends ApiInterface
{
    private const GROUPS = ['factureFournisseur:read', 'paiementFacture:read', 'fournisseur:read', 'bonReception:read', 'ref'];

    #[RequirePermission('factures', 'R')]
    #[Route('', name: 'api_achats_factures_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, FactureFournisseurRepositoryInterface $repo): JsonResponse
    {
        $list = $repo->search($tenant->requireEntreprise(), $request->query->get('statut'), $request->query->getInt('fournisseur') ?: null);
        return $this->responseData($list, [], [], self::GROUPS);
    }

    #[RequirePermission('factures', 'R')]
    #[Route('/{id}', name: 'api_achats_factures_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TenantContext $tenant, FactureFournisseurRepositoryInterface $repo): JsonResponse
    {
        $facture = $repo->findOneInEntreprise($id, $tenant->requireEntreprise());
        return $facture ? $this->responseData($facture, [], [], self::GROUPS) : $this->errorResponse(null, 'Facture introuvable.', 404);
    }

    #[RequirePermission('factures', 'C')]
    #[Route('', name: 'api_achats_factures_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em, FactureFournisseurRepositoryInterface $repo): JsonResponse
    {
        $data = $this->body($request);
        $entreprise = $tenant->requireEntreprise();

        if (empty(trim((string) ($data['numeroFacture'] ?? '')))) {
            return $this->errorResponse(null, 'Le numéro de facture est obligatoire.', 400);
        }
        $fournisseur = $em->getRepository(Fournisseur::class)->findOneBy(['id' => (int) ($data['fournisseur'] ?? 0), 'entreprise' => $entreprise]);
        if (!$fournisseur) {
            return $this->errorResponse(null, 'Fournisseur introuvable.', 404);
        }
        if ($em->getRepository(FactureFournisseur::class)->findOneBy(['entreprise' => $entreprise, 'fournisseur' => $fournisseur, 'numeroFacture' => trim($data['numeroFacture'])])) {
            return $this->errorResponse(null, 'Cette facture est déjà enregistrée pour ce fournisseur.', 400);
        }

        $bon = null;
        $montant = $data['montantTotal'] ?? null;
        if (!empty($data['bonReception'])) {
            $bon = $em->getRepository(BonReception::class)->findOneBy(['id' => (int) $data['bonReception'], 'entreprise' => $entreprise]);
            if (!$bon) {
                return $this->errorResponse(null, 'Bon de réception introuvable.', 404);
            }
            if ($montant === null || $montant === '') {
                $montant = '0';
                foreach ($bon->getLignes() as $l) {
                    $montant = bcadd($montant, bcmul((string) $l->getQuantiteRecue(), $l->getPrixUnitaireHt() ?? '0', 2), 2);
                }
            }
        }
        if (!is_numeric($montant) || (float) $montant <= 0) {
            return $this->errorResponse(null, 'Le montant total doit être positif.', 400);
        }

        $facture = (new FactureFournisseur())
            ->setEntreprise($entreprise)
            ->setCentre($tenant->getCentre())
            ->setNumeroFacture(trim($data['numeroFacture']))
            ->setFournisseur($fournisseur)
            ->setBonReception($bon)
            ->setMontantTotal(number_format((float) $montant, 2, '.', ''))
            ->setDateFacture(new \DateTimeImmutable($data['dateFacture'] ?? 'today'))
            ->setDateEcheance(!empty($data['dateEcheance']) ? new \DateTimeImmutable($data['dateEcheance']) : null);

        $repo->add($facture, true);

        return $this->setStatusCode(201)->responseData($facture, [], [], self::GROUPS);
    }

    #[RequirePermission(action: 'factures.payer')]
    #[Route('/{id}/paiements', name: 'api_achats_factures_paiement', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function payer(int $id, Request $request, TenantContext $tenant, FactureFournisseurRepositoryInterface $repo, EnregistrerPaiementUseCase $useCase): JsonResponse
    {
        $facture = $repo->findOneInEntreprise($id, $tenant->requireEntreprise());
        if (!$facture) {
            return $this->errorResponse(null, 'Facture introuvable.', 404);
        }

        try {
            $useCase->execute($facture, $this->body($request));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->setStatusCode(201)->setMessage('Règlement enregistré.')->responseData($facture, [], [], self::GROUPS);
    }
}

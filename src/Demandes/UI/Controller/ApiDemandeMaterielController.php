<?php

namespace App\Demandes\UI\Controller;

use App\Demandes\Application\Service\DemandeWorkflow;
use App\Demandes\Domain\Entity\DemandeMateriel;
use App\Demandes\Domain\Repository\DemandeMaterielRepositoryInterface;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/demandes')]
class ApiDemandeMaterielController extends ApiInterface
{
    private const GROUPS = ['demandeMateriel:read', 'ligneDemande:read', 'bonSortie:read', 'article:read', 'stock', 'utilisateur:read', 'service:read', 'ref'];

    /** Un employé ne voit que ses demandes ; un gestionnaire voit celles de tout le centre. */
    #[RequirePermission('demandes', 'R')]
    #[Route('', name: 'api_demandes_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo): JsonResponse
    {
        $user = $this->currentUser();
        $mineOnly = !$this->canDo('demandes.voir_toutes') || $request->query->getBoolean('mes');
        $list = $repo->search($tenant->requireCentre(), $mineOnly ? $user : null, $request->query->get('statut'));

        return $this->responseData($list, [], ['total' => count($list)], self::GROUPS);
    }

    #[RequirePermission('demandes', 'R')]
    #[Route('/{id}', name: 'api_demandes_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo): JsonResponse
    {
        $demande = $this->findVisible($id, $tenant, $repo);
        return $demande ? $this->responseData($demande, [], [], self::GROUPS) : $this->errorResponse(null, 'Demande introuvable.', 404);
    }

    #[RequirePermission('demandes', 'C')]
    #[Route('', name: 'api_demandes_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, DemandeWorkflow $workflow): JsonResponse
    {
        return $this->run(fn () => $workflow->soumettre($this->currentUser(), $tenant->requireCentre(), $this->body($request)), 201, 'Demande envoyée.');
    }

    #[RequirePermission(action: 'demandes.valider')]
    #[Route('/{id}/valider', name: 'api_demandes_validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function valider(int $id, Request $request, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo, DemandeWorkflow $workflow): JsonResponse
    {
        return $this->action($id, $tenant, $repo, fn (DemandeMateriel $d) => $workflow->valider($d, $this->currentUser(), $this->body($request)), 'Demande validée.');
    }

    #[RequirePermission(action: 'demandes.valider')]
    #[Route('/{id}/refuser', name: 'api_demandes_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuser(int $id, Request $request, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo, DemandeWorkflow $workflow): JsonResponse
    {
        return $this->action($id, $tenant, $repo, fn (DemandeMateriel $d) => $workflow->refuser($d, $this->currentUser(), $this->body($request)['motif'] ?? null), 'Demande refusée.');
    }

    #[RequirePermission(action: 'demandes.livrer')]
    #[Route('/{id}/livrer', name: 'api_demandes_deliver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function livrer(int $id, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo, DemandeWorkflow $workflow): JsonResponse
    {
        return $this->action($id, $tenant, $repo, function (DemandeMateriel $d) use ($workflow) {
            $workflow->livrer($d, $this->currentUser());
            return $d;
        }, 'Bon de sortie émis.');
    }

    /** Le demandeur confirme la réception depuis son espace (un gestionnaire peut le faire pour lui). */
    #[RequirePermission('demandes', 'R')]
    #[Route('/{id}/accuse-reception', name: 'api_demandes_receipt', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accuserReception(int $id, Request $request, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo, DemandeWorkflow $workflow): JsonResponse
    {
        $demande = $this->findVisible($id, $tenant, $repo);
        if (!$demande) {
            return $this->errorResponse(null, 'Demande introuvable.', 404);
        }
        $user = $this->currentUser();
        if ($demande->getDemandeur()->getId() !== $user->getId() && !$this->canDo('demandes.voir_toutes')) {
            return $this->errorResponse(null, "Seul le demandeur peut accuser réception.", 403);
        }

        return $this->run(fn () => $workflow->accuserReception($demande, $user, $this->body($request)['confirmation'] ?? null), 200, 'Réception confirmée, stock mis à jour.');
    }

    #[RequirePermission('demandes', 'D')]
    #[Route('/{id}', name: 'api_demandes_cancel', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function annuler(int $id, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo, DemandeWorkflow $workflow): JsonResponse
    {
        $demande = $this->findVisible($id, $tenant, $repo);
        if (!$demande) {
            return $this->errorResponse(null, 'Demande introuvable.', 404);
        }
        if ($demande->getDemandeur()->getId() !== $this->currentUser()->getId() && !$this->canDo('demandes.voir_toutes')) {
            return $this->errorResponse(null, 'Action non autorisée.', 403);
        }

        try {
            $workflow->annuler($demande);
        } catch (\DomainException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->setMessage('Demande annulée.')->responseData([]);
    }

    private function findVisible(int $id, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo): ?DemandeMateriel
    {
        $demande = $repo->findOneInCentre($id, $tenant->requireCentre());
        if ($demande && !$this->canDo('demandes.voir_toutes') && $demande->getDemandeur()->getId() !== $this->currentUser()->getId()) {
            return null;
        }
        return $demande;
    }

    private function action(int $id, TenantContext $tenant, DemandeMaterielRepositoryInterface $repo, callable $fn, string $message): JsonResponse
    {
        $demande = $repo->findOneInCentre($id, $tenant->requireCentre());
        if (!$demande) {
            return $this->errorResponse(null, 'Demande introuvable.', 404);
        }

        return $this->run(fn () => $fn($demande), 200, $message);
    }

    private function run(callable $fn, int $status, string $message): JsonResponse
    {
        try {
            $demande = $fn();
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->setStatusCode($status)->setMessage($message)->responseData($demande, [], [], self::GROUPS);
    }
}

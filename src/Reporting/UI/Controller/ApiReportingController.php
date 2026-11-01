<?php

namespace App\Reporting\UI\Controller;

use App\Reporting\Application\Service\ReportingService;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/reporting')]
class ApiReportingController extends ApiInterface
{
    #[RequirePermission('dashboard', 'R')]
    #[Route('/dashboard', name: 'api_reporting_dashboard', methods: ['GET'])]
    public function dashboard(TenantContext $tenant, ReportingService $service): JsonResponse
    {
        if (!$this->canDo('dashboard.stats')) {
            return $this->errorResponse(null, 'Les indicateurs globaux ne sont pas accessibles avec votre rôle.', 403);
        }

        return $this->responseData($service->dashboard($tenant->requireEntreprise(), $tenant->requireCentre()));
    }

    #[RequirePermission('alertes', 'R')]
    #[Route('/reapprovisionnement', name: 'api_reporting_reappro', methods: ['GET'])]
    public function reapprovisionnement(TenantContext $tenant, ReportingService $service): JsonResponse
    {
        return $this->responseData($service->reapprovisionnement($tenant->requireCentre()));
    }

    #[RequirePermission('rapports', 'R')]
    #[Route('/consommation', name: 'api_reporting_consommation', methods: ['GET'])]
    public function consommation(Request $request, TenantContext $tenant, ReportingService $service): JsonResponse
    {
        try {
            $du = new \DateTimeImmutable($request->query->get('du', 'first day of january this year'));
            $au = new \DateTimeImmutable($request->query->get('au', 'today'));
        } catch (\Exception) {
            return $this->errorResponse(null, 'Période invalide.', 400);
        }

        return $this->responseData($service->consommation($tenant->requireCentre(), $du, $au, min(50, $request->query->getInt('limit', 10))));
    }

    #[RequirePermission('factures', 'R')]
    #[Route('/echeancier', name: 'api_reporting_echeancier', methods: ['GET'])]
    public function echeancier(TenantContext $tenant, ReportingService $service): JsonResponse
    {
        return $this->responseData($service->echeancier($tenant->requireEntreprise()));
    }
}

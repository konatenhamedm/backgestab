<?php

namespace App\Achats\UI\Controller;

use App\Achats\Application\UseCase\CreateBonReceptionUseCase;
use App\Achats\Domain\Entity\BonReception;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/achats/bons-reception')]
class ApiBonReceptionController extends ApiInterface
{
    private const GROUPS = ['bonReception:read', 'ligneBonReception:read', 'fournisseur:read', 'commande:read', 'article:read', 'ref'];

    #[RequirePermission('receptions', 'R', or: [['factures', 'C']])]
    #[Route('', name: 'api_achats_bons_reception_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('b', 'f', 'l', 'a')->from(BonReception::class, 'b')
            ->join('b.fournisseur', 'f')->leftJoin('b.lignes', 'l')->leftJoin('l.article', 'a')
            ->where('b.centre = :centre')->setParameter('centre', $tenant->requireCentre())
            ->orderBy('b.dateEntree', 'DESC')->addOrderBy('b.id', 'DESC');

        if ($fournisseur = $request->query->getInt('fournisseur')) {
            $qb->andWhere('f.id = :f')->setParameter('f', $fournisseur);
        }

        return $this->responseData($qb->getQuery()->getResult(), [], [], self::GROUPS);
    }

    #[RequirePermission('receptions', 'R', or: [['factures', 'C']])]
    #[Route('/{id}', name: 'api_achats_bons_reception_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $bon = $em->getRepository(BonReception::class)->findOneBy(['id' => $id, 'centre' => $tenant->requireCentre()]);
        return $bon ? $this->responseData($bon, [], [], self::GROUPS) : $this->errorResponse(null, 'Bon de réception introuvable.', 404);
    }

    #[RequirePermission('receptions', 'C')]
    #[Route('', name: 'api_achats_bons_reception_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, CreateBonReceptionUseCase $useCase): JsonResponse
    {
        try {
            $bon = $useCase->execute($tenant->requireCentre(), $this->body($request));
        } catch (\InvalidArgumentException|\DomainException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->setStatusCode(201)->setMessage('Réception enregistrée, stock mis à jour.')->responseData($bon, [], [], self::GROUPS);
    }
}

<?php

namespace App\Stock\UI\Controller;

use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use App\Stock\Domain\Entity\MouvementStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/stock/mouvements')]
class ApiMouvementStockController extends ApiInterface
{
    #[RequirePermission('mouvements', 'R')]
    #[Route('', name: 'api_stock_mouvements_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('m', 'a')->from(MouvementStock::class, 'm')->join('m.article', 'a')
            ->where('m.centre = :centre')->setParameter('centre', $tenant->requireCentre())
            ->orderBy('m.dateMouvement', 'DESC')->addOrderBy('m.id', 'DESC')
            ->setMaxResults(min(500, $request->query->getInt('limit', 100)));

        if ($type = $request->query->get('type')) {
            $qb->andWhere('m.type = :t')->setParameter('t', $type);
        }
        if ($article = $request->query->getInt('article')) {
            $qb->andWhere('a.id = :a')->setParameter('a', $article);
        }

        return $this->responseData($qb->getQuery()->getResult(), [], [], ['mouvementStock:read', 'ref']);
    }
}

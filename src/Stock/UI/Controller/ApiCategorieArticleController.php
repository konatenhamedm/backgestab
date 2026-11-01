<?php

namespace App\Stock\UI\Controller;

use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use App\Stock\Domain\Entity\CategorieArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/stock/categories')]
class ApiCategorieArticleController extends ApiInterface
{
    #[RequirePermission('articles', 'R', or: [['demandes', 'C']])]
    #[Route('', name: 'api_stock_categories_index', methods: ['GET'])]
    public function index(TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $list = $em->getRepository(CategorieArticle::class)->findBy(['entreprise' => $tenant->requireEntreprise(), 'isActive' => true], ['nom' => 'ASC']);
        return $this->responseData($list, [], [], ['categorieArticle:read']);
    }

    #[RequirePermission('articles', 'C')]
    #[Route('', name: 'api_stock_categories_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        if (empty(trim((string) ($data['nom'] ?? '')))) {
            return $this->errorResponse(null, 'Le nom de la catégorie est obligatoire.', 400);
        }

        $entity = (new CategorieArticle())
            ->setEntreprise($tenant->requireEntreprise())
            ->setNom(trim($data['nom']))
            ->setDescription($data['description'] ?? null);
        $em->persist($entity);
        $em->flush();

        return $this->setStatusCode(201)->responseData($entity, [], [], ['categorieArticle:read']);
    }

    #[RequirePermission('articles', 'U')]
    #[Route('/{id}', name: 'api_stock_categories_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(CategorieArticle::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Catégorie introuvable.', 404);
        }
        $data = $this->body($request);
        if (!empty($data['nom'])) $entity->setNom($data['nom']);
        if (array_key_exists('description', $data)) $entity->setDescription($data['description']);
        $em->flush();

        return $this->responseData($entity, [], [], ['categorieArticle:read']);
    }

    #[RequirePermission('articles', 'D')]
    #[Route('/{id}', name: 'api_stock_categories_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(CategorieArticle::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Catégorie introuvable.', 404);
        }
        $entity->setIsActive(false);
        $em->flush();

        return $this->setMessage('Catégorie supprimée.')->responseData([]);
    }
}

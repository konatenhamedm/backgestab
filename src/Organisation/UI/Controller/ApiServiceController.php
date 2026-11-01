<?php

namespace App\Organisation\UI\Controller;

use App\Organisation\Domain\Entity\Service;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/organisation/services')]
class ApiServiceController extends ApiInterface
{
    #[RequirePermission('services', 'R', or: [['utilisateurs', 'C'], ['utilisateurs', 'U']])]
    #[Route('', name: 'api_organisation_services_index', methods: ['GET'])]
    public function index(TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $list = $em->getRepository(Service::class)->findBy(
            ['entreprise' => $tenant->requireEntreprise(), 'isActive' => true],
            ['nom' => 'ASC']
        );
        return $this->responseData($list, [], [], ['service:read']);
    }

    #[RequirePermission('services', 'C')]
    #[Route('', name: 'api_organisation_services_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        if (empty(trim((string) ($data['nom'] ?? '')))) {
            return $this->errorResponse(null, 'Le nom est obligatoire.', 400);
        }

        $entity = (new Service())
            ->setEntreprise($tenant->requireEntreprise())
            ->setNom(trim($data['nom']))
            ->setCode($data['code'] ?? null);

        $em->persist($entity);
        $em->flush();

        return $this->setStatusCode(201)->responseData($entity, [], [], ['service:read']);
    }

    #[RequirePermission('services', 'U')]
    #[Route('/{id}', name: 'api_organisation_services_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(Service::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Service introuvable.', 404);
        }

        $data = $this->body($request);
        if (!empty($data['nom'])) $entity->setNom($data['nom']);
        if (array_key_exists('code', $data)) $entity->setCode($data['code']);

        $em->flush();

        return $this->responseData($entity, [], [], ['service:read']);
    }

    #[RequirePermission('services', 'D')]
    #[Route('/{id}', name: 'api_organisation_services_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(Service::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Service introuvable.', 404);
        }
        $entity->setIsActive(false);
        $em->flush();

        return $this->setMessage('Service supprimé.')->responseData([]);
    }
}

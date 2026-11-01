<?php

namespace App\Organisation\UI\Controller;

use App\Organisation\Domain\Entity\Centre;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/organisation/centres')]
class ApiCentreController extends ApiInterface
{
    #[RequirePermission('centres', 'R', or: [['utilisateurs', 'C'], ['utilisateurs', 'U']])]
    #[Route('', name: 'api_organisation_centres_index', methods: ['GET'])]
    public function index(TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $list = $em->getRepository(Centre::class)->findBy(
            ['entreprise' => $tenant->requireEntreprise(), 'isActive' => true],
            ['nom' => 'ASC']
        );
        return $this->responseData($list, [], [], ['centre:read']);
    }

    #[RequirePermission('centres', 'C')]
    #[Route('', name: 'api_organisation_centres_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        if (empty(trim((string) ($data['nom'] ?? '')))) {
            return $this->errorResponse(null, 'Le nom est obligatoire.', 400);
        }

        $entreprise = $tenant->requireEntreprise();
        $actifs = (int) $em->createQuery('SELECT COUNT(c.id) FROM ' . Centre::class . ' c WHERE c.entreprise = :e AND c.isActive = true')->setParameter('e', $entreprise)->getSingleScalarResult();
        if ($actifs >= $entreprise->getMaxCentres()) {
            return $this->errorResponse(null, sprintf('Limite de votre abonnement atteinte (%d centres). Contactez l\'administrateur de la plateforme.', $entreprise->getMaxCentres()), 403);
        }

        $entity = (new Centre())
            ->setEntreprise($entreprise)
            ->setNom(trim($data['nom']))
            ->setCode($data['code'] ?? null);
            $entity->setAdresse($data['adresse'] ?? null);
        $em->persist($entity);
        $em->flush();

        return $this->setStatusCode(201)->responseData($entity, [], [], ['centre:read']);
    }

    #[RequirePermission('centres', 'U')]
    #[Route('/{id}', name: 'api_organisation_centres_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(Centre::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Centre introuvable.', 404);
        }

        $data = $this->body($request);
        if (!empty($data['nom'])) $entity->setNom($data['nom']);
        if (array_key_exists('code', $data)) $entity->setCode($data['code']);
        if (array_key_exists('adresse', $data)) $entity->setAdresse($data['adresse']);
        $em->flush();

        return $this->responseData($entity, [], [], ['centre:read']);
    }

    #[RequirePermission('centres', 'D')]
    #[Route('/{id}', name: 'api_organisation_centres_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(Centre::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Centre introuvable.', 404);
        }
        $entity->setIsActive(false);
        $em->flush();

        return $this->setMessage('Centre supprimé.')->responseData([]);
    }
}

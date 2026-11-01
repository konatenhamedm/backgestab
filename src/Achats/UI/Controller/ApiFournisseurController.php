<?php

namespace App\Achats\UI\Controller;

use App\Achats\Domain\Entity\Fournisseur;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/achats/fournisseurs')]
class ApiFournisseurController extends ApiInterface
{
    #[RequirePermission('fournisseurs', 'R', or: [['commandes', 'C'], ['receptions', 'C'], ['factures', 'C']])]
    #[Route('', name: 'api_achats_fournisseurs_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('f')->from(Fournisseur::class, 'f')
            ->where('f.entreprise = :e')->andWhere('f.isActive = true')
            ->setParameter('e', $tenant->requireEntreprise())
            ->orderBy('f.raisonSociale', 'ASC');

        if ($search = $request->query->get('search')) {
            $qb->andWhere('LOWER(f.raisonSociale) LIKE :s OR LOWER(f.email) LIKE :s OR f.telephone LIKE :s')
               ->setParameter('s', '%' . mb_strtolower($search) . '%');
        }

        return $this->responseData($qb->getQuery()->getResult(), [], [], ['fournisseur:read']);
    }

    #[RequirePermission('fournisseurs', 'C')]
    #[Route('', name: 'api_achats_fournisseurs_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        if (empty(trim((string) ($data['raisonSociale'] ?? '')))) {
            return $this->errorResponse(null, 'La raison sociale est obligatoire.', 400);
        }

        $entity = (new Fournisseur())->setEntreprise($tenant->requireEntreprise());
        $this->hydrate($entity, $data);
        $em->persist($entity);
        $em->flush();

        return $this->setStatusCode(201)->responseData($entity, [], [], ['fournisseur:read']);
    }

    #[RequirePermission('fournisseurs', 'U')]
    #[Route('/{id}', name: 'api_achats_fournisseurs_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(Fournisseur::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Fournisseur introuvable.', 404);
        }
        $this->hydrate($entity, $this->body($request));
        $em->flush();

        return $this->responseData($entity, [], [], ['fournisseur:read']);
    }

    #[RequirePermission('fournisseurs', 'D')]
    #[Route('/{id}', name: 'api_achats_fournisseurs_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entity = $em->getRepository(Fournisseur::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$entity) {
            return $this->errorResponse(null, 'Fournisseur introuvable.', 404);
        }
        $entity->setIsActive(false);
        $em->flush();

        return $this->setMessage('Fournisseur supprimé.')->responseData([]);
    }

    private function hydrate(Fournisseur $entity, array $data): void
    {
        if (!empty($data['raisonSociale'])) $entity->setRaisonSociale(trim($data['raisonSociale']));
        foreach (['contactNom', 'email', 'telephone', 'adresse'] as $field) {
            if (array_key_exists($field, $data)) {
                $entity->{'set' . ucfirst($field)}($data[$field] ?: null);
            }
        }
    }
}

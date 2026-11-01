<?php

namespace App\Organisation\UI\Controller;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Application\Service\EntrepriseProvisioner;
use App\Security\Application\UseCase\RegisterEntrepriseUseCase;
use App\Security\Domain\Attribute\RequirePermission;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/organisation')]
class ApiEntrepriseController extends ApiInterface
{
    // ---- Espace de l'entreprise courante ----

    #[RequirePermission('entreprise', 'R', or: [['dashboard', 'R']])]
    #[Route('/entreprise', name: 'api_organisation_entreprise_show', methods: ['GET'])]
    public function current(TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entreprise = $tenant->getEntreprise();
        if (!$entreprise) {
            return $this->errorResponse(null, 'Aucune entreprise active.', 404);
        }

        return $this->responseData($this->toArray($entreprise, $em));
    }

    #[RequirePermission('entreprise', 'U')]
    #[Route('/entreprise', name: 'api_organisation_entreprise_update', methods: ['PUT', 'PATCH'])]
    public function updateCurrent(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $entreprise = $tenant->requireEntreprise();
        $data = $this->body($request);

        if (isset($data['nom']) && trim($data['nom']) !== '') $entreprise->setNom($data['nom']);
        if (array_key_exists('email', $data)) $entreprise->setEmail($data['email']);
        if (array_key_exists('telephone', $data)) $entreprise->setTelephone($data['telephone']);
        if (array_key_exists('adresse', $data)) $entreprise->setAdresse($data['adresse']);
        if (!empty($data['devise'])) $entreprise->setDevise($data['devise']);
        $em->flush();

        return $this->responseData($this->toArray($entreprise, $em));
    }

    // ---- Administration de la plateforme (SaaS) : super administrateur ----

    #[RequirePermission(superAdmin: true)]
    #[Route('/entreprises', name: 'api_organisation_entreprises_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): JsonResponse
    {
        $list = $em->getRepository(Entreprise::class)->findBy([], ['nom' => 'ASC']);

        return $this->responseData(array_map(fn (Entreprise $e) => $this->toArray($e, $em), $list));
    }

    #[RequirePermission(superAdmin: true)]
    #[Route('/entreprises/plateforme', name: 'api_organisation_plateforme', methods: ['GET'])]
    public function plateforme(EntityManagerInterface $em): JsonResponse
    {
        $count = fn (string $dql) => (int) $em->createQuery($dql)->getSingleScalarResult();
        $today = new \DateTimeImmutable('today');
        $bientot = $today->modify('+14 days');

        return $this->responseData([
            'entreprises' => $count('SELECT COUNT(e.id) FROM ' . Entreprise::class . ' e'),
            'entreprisesActives' => $count('SELECT COUNT(e.id) FROM ' . Entreprise::class . ' e WHERE e.isActive = true'),
            'entreprisesExpirees' => (int) $em->createQuery('SELECT COUNT(e.id) FROM ' . Entreprise::class . ' e WHERE e.dateExpiration < :t')->setParameter('t', $today)->getSingleScalarResult(),
            'expirentBientot' => (int) $em->createQuery('SELECT COUNT(e.id) FROM ' . Entreprise::class . ' e WHERE e.dateExpiration >= :t AND e.dateExpiration <= :b')->setParameter('t', $today)->setParameter('b', $bientot)->getSingleScalarResult(),
            'centres' => $count('SELECT COUNT(c.id) FROM ' . Centre::class . ' c WHERE c.isActive = true'),
            'utilisateurs' => $count('SELECT COUNT(u.id) FROM ' . Utilisateur::class . ' u WHERE u.isActive = true'),
            'plans' => EntrepriseProvisioner::PLANS,
            'repartitionPlans' => $em->createQuery('SELECT e.plan AS plan, COUNT(e.id) AS n FROM ' . Entreprise::class . ' e GROUP BY e.plan')->getArrayResult(),
        ]);
    }

    #[RequirePermission(superAdmin: true)]
    #[Route('/entreprises', name: 'api_organisation_entreprises_create', methods: ['POST'])]
    public function create(Request $request, RegisterEntrepriseUseCase $useCase, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        try {
            $admin = $useCase->execute($data, $data['plan'] ?? 'ESSAI');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->setStatusCode(201)->responseData($this->toArray($admin->getEntreprise(), $em));
    }

    #[RequirePermission(superAdmin: true)]
    #[Route('/entreprises/{id}', name: 'api_organisation_entreprises_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, EntityManagerInterface $em, EntrepriseProvisioner $provisioner): JsonResponse
    {
        $entreprise = $em->getRepository(Entreprise::class)->find($id);
        if (!$entreprise) {
            return $this->errorResponse(null, 'Entreprise introuvable.', 404);
        }

        $data = $this->body($request);
        if (!empty($data['nom'])) $entreprise->setNom($data['nom']);
        if (array_key_exists('email', $data)) $entreprise->setEmail($data['email']);
        if (!empty($data['plan']) && $data['plan'] !== $entreprise->getPlan()) {
            $provisioner->appliquerPlan($entreprise, $data['plan']);
        }
        if (isset($data['maxCentres'])) $entreprise->setMaxCentres(max(1, (int) $data['maxCentres']));
        if (isset($data['maxUtilisateurs'])) $entreprise->setMaxUtilisateurs(max(1, (int) $data['maxUtilisateurs']));
        if (array_key_exists('dateExpiration', $data)) {
            $entreprise->setDateExpiration($data['dateExpiration'] ? new \DateTimeImmutable($data['dateExpiration']) : null);
        }
        if (isset($data['isActive'])) $entreprise->setIsActive((bool) $data['isActive']);
        $em->flush();

        return $this->responseData($this->toArray($entreprise, $em));
    }

    private function toArray(Entreprise $e, EntityManagerInterface $em): array
    {
        $count = fn (string $class, string $extra = '') => (int) $em->createQuery("SELECT COUNT(x.id) FROM $class x WHERE x.entreprise = :e $extra")->setParameter('e', $e)->getSingleScalarResult();

        return [
            'id' => $e->getId(), 'nom' => $e->getNom(), 'code' => $e->getCode(), 'email' => $e->getEmail(), 'telephone' => $e->getTelephone(),
            'adresse' => $e->getAdresse(), 'devise' => $e->getDevise(), 'isActive' => $e->isActive(),
            'plan' => $e->getPlan(), 'maxCentres' => $e->getMaxCentres(), 'maxUtilisateurs' => $e->getMaxUtilisateurs(),
            'dateExpiration' => $e->getDateExpiration()?->format('Y-m-d'), 'expire' => $e->isExpire(),
            'nbCentres' => $count(Centre::class, 'AND x.isActive = true'),
            'nbUtilisateurs' => $count(Utilisateur::class, 'AND x.isActive = true'),
            'createdAt' => $e->getCreatedAt()?->format('c'),
        ];
    }
}

<?php

namespace App\Organisation\UI\Controller;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Service;
use App\Security\Domain\Attribute\RequirePermission;
use App\Security\Domain\Entity\Role;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/organisation/utilisateurs')]
class ApiUtilisateurController extends ApiInterface
{
    private const GROUPS = ['utilisateur:read', 'role:read', 'centre:read', 'service:read', 'ref'];

    #[RequirePermission('utilisateurs', 'R')]
    #[Route('', name: 'api_organisation_utilisateurs_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, UtilisateurRepositoryInterface $repo): JsonResponse
    {
        $users = $repo->findByEntreprise($tenant->requireEntreprise());
        if ($roleId = $request->query->getInt('role')) {
            $users = array_values(array_filter($users, fn (Utilisateur $u) => $u->getRole()?->getId() === $roleId));
        }

        return $this->responseData($users, [], ['total' => count($users)], self::GROUPS);
    }

    #[RequirePermission('utilisateurs', 'C')]
    #[Route('', name: 'api_organisation_utilisateurs_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, UtilisateurRepositoryInterface $repo, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        $entreprise = $tenant->requireEntreprise();

        foreach (['email', 'nom', 'prenom'] as $field) {
            if (empty(trim((string) ($data[$field] ?? '')))) {
                return $this->errorResponse(null, sprintf('Le champ "%s" est obligatoire.', $field), 400);
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->errorResponse(null, "L'adresse email est invalide.", 400);
        }
        if ($repo->findByEmail($data['email'])) {
            return $this->errorResponse(null, 'Cette adresse email est déjà utilisée.', 400);
        }

        $actifs = (int) $em->createQuery('SELECT COUNT(u.id) FROM ' . Utilisateur::class . ' u WHERE u.entreprise = :e AND u.isActive = true')->setParameter('e', $entreprise)->getSingleScalarResult();
        if ($actifs >= $entreprise->getMaxUtilisateurs()) {
            return $this->errorResponse(null, sprintf('Limite de votre abonnement atteinte (%d utilisateurs). Contactez l\'administrateur de la plateforme.', $entreprise->getMaxUtilisateurs()), 403);
        }

        $role = $em->getRepository(Role::class)->findOneBy(['id' => (int) ($data['role'] ?? 0), 'entreprise' => $entreprise, 'isActive' => true]);
        if (!$role) {
            return $this->errorResponse(null, 'Choisissez un rôle valide.', 400);
        }

        $password = $data['password'] ?? bin2hex(random_bytes(4)) . 'Aa1!';
        $user = (new Utilisateur())
            ->setEmail($data['email'])->setNom($data['nom'])->setPrenom($data['prenom'])->setTelephone($data['telephone'] ?? null)
            ->setRole($role)->setEntreprise($entreprise)->setMustChangePassword(true);
        $user->setPassword($hasher->hashPassword($user, $password));
        $this->applyAffectation($user, $data, $tenant, $em);

        $repo->add($user, true);

        // Le mot de passe provisoire n'est renvoyé qu'à la création.
        return $this->setStatusCode(201)->responseData([
            'utilisateur' => json_decode($this->serializer->serialize($user, 'json', ['groups' => self::GROUPS]), true),
            'password_provisoire' => $password,
        ]);
    }

    #[RequirePermission('utilisateurs', 'U')]
    #[Route('/{id}', name: 'api_organisation_utilisateurs_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $user = $em->getRepository(Utilisateur::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$user) {
            return $this->errorResponse(null, 'Utilisateur introuvable.', 404);
        }

        $data = $this->body($request);
        if (!empty($data['nom'])) $user->setNom($data['nom']);
        if (!empty($data['prenom'])) $user->setPrenom($data['prenom']);
        if (array_key_exists('telephone', $data)) $user->setTelephone($data['telephone']);

        if (isset($data['role'])) {
            $role = $em->getRepository(Role::class)->findOneBy(['id' => (int) $data['role'], 'entreprise' => $tenant->requireEntreprise(), 'isActive' => true]);
            if (!$role) {
                return $this->errorResponse(null, 'Rôle invalide.', 400);
            }
            // On ne peut pas retirer le dernier administrateur de l'entreprise
            if ($user->getRole()?->isSysteme() && !$role->isSysteme() && $this->countAdmins($em, $tenant) <= 1) {
                return $this->errorResponse(null, "Impossible : c'est le dernier administrateur de l'entreprise.", 400);
            }
            $user->setRole($role);
        }
        if (isset($data['isActive'])) {
            $active = (bool) $data['isActive'];
            if (!$active && $user->getId() === $this->currentUser()->getId()) {
                return $this->errorResponse(null, 'Vous ne pouvez pas désactiver votre propre compte.', 400);
            }
            $user->setIsActive($active);
        }
        if (!empty($data['password'])) {
            $user->setPassword($hasher->hashPassword($user, $data['password']));
            $user->setMustChangePassword(true);
        }
        $this->applyAffectation($user, $data, $tenant, $em);
        $em->flush();

        return $this->responseData($user, [], [], self::GROUPS);
    }

    private function countAdmins(EntityManagerInterface $em, TenantContext $tenant): int
    {
        return (int) $em->createQuery('SELECT COUNT(u.id) FROM ' . Utilisateur::class . ' u JOIN u.role r WHERE u.entreprise = :e AND r.systeme = true AND u.isActive = true')
            ->setParameter('e', $tenant->requireEntreprise())->getSingleScalarResult();
    }

    private function applyAffectation(Utilisateur $user, array $data, TenantContext $tenant, EntityManagerInterface $em): void
    {
        $entreprise = $tenant->requireEntreprise();
        if (array_key_exists('centres', $data)) {
            $ids = array_map('intval', (array) $data['centres']);
            $centres = $ids ? $em->getRepository(Centre::class)->findBy(['id' => $ids, 'entreprise' => $entreprise]) : [];
            $user->setCentres($centres);
        }
        if (array_key_exists('service', $data)) {
            $service = $data['service'] ? $em->getRepository(Service::class)->findOneBy(['id' => (int) $data['service'], 'entreprise' => $entreprise]) : null;
            $user->setService($service);
        }
    }
}

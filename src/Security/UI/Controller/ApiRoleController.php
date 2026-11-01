<?php

namespace App\Security\UI\Controller;

use App\Security\Domain\Attribute\RequirePermission;
use App\Security\Domain\Entity\Role;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\PermissionCatalog;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Rôles et permissions dynamiques : chaque entreprise définit ses propres rôles
 * (matrice module × CRUD + actions spéciales).
 */
#[Route('/api/security')]
class ApiRoleController extends ApiInterface
{
    /** Catalogue des modules/actions disponibles pour construire la matrice. */
    #[RequirePermission('roles', 'R', or: [['utilisateurs', 'C'], ['utilisateurs', 'U']])]
    #[Route('/catalogue', name: 'api_security_catalogue', methods: ['GET'])]
    public function catalogue(): JsonResponse
    {
        return $this->responseData(['groupes' => PermissionCatalog::groupes()]);
    }

    #[RequirePermission('roles', 'R', or: [['utilisateurs', 'C'], ['utilisateurs', 'U']])]
    #[Route('/roles', name: 'api_security_roles_index', methods: ['GET'])]
    public function index(TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $roles = $em->getRepository(Role::class)->findBy(['entreprise' => $tenant->requireEntreprise(), 'isActive' => true], ['systeme' => 'DESC', 'nom' => 'ASC']);
        $counts = $em->createQuery('SELECT IDENTITY(u.role) AS r, COUNT(u.id) AS n FROM ' . Utilisateur::class . ' u WHERE u.entreprise = :e GROUP BY u.role')
            ->setParameter('e', $tenant->requireEntreprise())->getArrayResult();
        $byRole = array_column($counts, 'n', 'r');

        return $this->responseData(array_map(fn (Role $r) => $this->toArray($r, (int) ($byRole[$r->getId()] ?? 0)), $roles));
    }

    #[RequirePermission('roles', 'C')]
    #[Route('/roles', name: 'api_security_roles_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $data = $this->body($request);
        $nom = trim((string) ($data['nom'] ?? ''));
        if ($nom === '') {
            return $this->errorResponse(null, 'Le nom du rôle est obligatoire.', 400);
        }
        $entreprise = $tenant->requireEntreprise();
        $code = $this->uniqueCode($em, $entreprise, $nom);

        $role = (new Role())->setEntreprise($entreprise)->setCode($code)->setNom($nom)
            ->setDescription($data['description'] ?? null)
            ->setPermissions(PermissionCatalog::sanitizePermissions((array) ($data['permissions'] ?? [])))
            ->setActions(PermissionCatalog::sanitizeActions((array) ($data['actions'] ?? [])));
        $em->persist($role);
        $em->flush();

        return $this->setStatusCode(201)->responseData($this->toArray($role, 0));
    }

    #[RequirePermission('roles', 'U')]
    #[Route('/roles/{id}', name: 'api_security_roles_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $role = $em->getRepository(Role::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$role) {
            return $this->errorResponse(null, 'Rôle introuvable.', 404);
        }
        if ($role->isSysteme()) {
            return $this->errorResponse(null, 'Le rôle Administrateur ne peut pas être modifié.', 400);
        }

        $data = $this->body($request);
        if (!empty(trim((string) ($data['nom'] ?? '')))) $role->setNom(trim($data['nom']));
        if (array_key_exists('description', $data)) $role->setDescription($data['description']);
        if (isset($data['permissions'])) $role->setPermissions(PermissionCatalog::sanitizePermissions((array) $data['permissions']));
        if (isset($data['actions'])) $role->setActions(PermissionCatalog::sanitizeActions((array) $data['actions']));
        $em->flush();

        return $this->responseData($this->toArray($role, $this->countUsers($em, $role)));
    }

    #[RequirePermission('roles', 'C')]
    #[Route('/roles/{id}/dupliquer', name: 'api_security_roles_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dupliquer(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $source = $em->getRepository(Role::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$source) {
            return $this->errorResponse(null, 'Rôle introuvable.', 404);
        }
        $nom = 'Copie de ' . $source->getNom();
        $copy = (new Role())->setEntreprise($source->getEntreprise())->setNom($nom)->setCode($this->uniqueCode($em, $source->getEntreprise(), $nom))
            ->setDescription($source->getDescription())->setPermissions($source->getPermissions())->setActions($source->getActions());
        if ($source->isSysteme()) {
            // Dupliquer l'administrateur donne un rôle équivalent mais modifiable : tous les droits explicitement
            $perms = [];
            foreach (PermissionCatalog::modules() as $code => $m) {
                $perms[$code] = implode('', array_values(array_intersect(['C', 'R', 'U', 'D'], $m['letters'])));
            }
            $copy->setPermissions($perms)->setActions(PermissionCatalog::actions());
        }
        $em->persist($copy);
        $em->flush();

        return $this->setStatusCode(201)->responseData($this->toArray($copy, 0));
    }

    #[RequirePermission('roles', 'D')]
    #[Route('/roles/{id}', name: 'api_security_roles_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $role = $em->getRepository(Role::class)->findOneBy(['id' => $id, 'entreprise' => $tenant->requireEntreprise()]);
        if (!$role) {
            return $this->errorResponse(null, 'Rôle introuvable.', 404);
        }
        if ($role->isSysteme()) {
            return $this->errorResponse(null, 'Le rôle Administrateur ne peut pas être supprimé.', 400);
        }
        if (($n = $this->countUsers($em, $role)) > 0) {
            return $this->errorResponse(null, sprintf('Ce rôle est attribué à %d utilisateur(s) : réaffectez-les avant de le supprimer.', $n), 400);
        }
        $role->setIsActive(false);
        $em->flush();

        return $this->setMessage('Rôle supprimé.')->responseData([]);
    }

    private function countUsers(EntityManagerInterface $em, Role $role): int
    {
        return (int) $em->createQuery('SELECT COUNT(u.id) FROM ' . Utilisateur::class . ' u WHERE u.role = :r')->setParameter('r', $role)->getSingleScalarResult();
    }

    private function uniqueCode(EntityManagerInterface $em, $entreprise, string $nom): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $nom) ?: $nom), 0, 40)) ?: 'ROLE';
        $code = $base;
        $i = 1;
        while ($em->getRepository(Role::class)->findOneBy(['entreprise' => $entreprise, 'code' => $code])) {
            $code = $base . '_' . ++$i;
        }
        return $code;
    }

    private function toArray(Role $r, int $users): array
    {
        return [
            'id' => $r->getId(), 'code' => $r->getCode(), 'nom' => $r->getNom(), 'description' => $r->getDescription(),
            'systeme' => $r->isSysteme(), 'permissions' => (object) $r->getPermissions(), 'actions' => $r->getActions(), 'utilisateurs' => $users,
        ];
    }
}

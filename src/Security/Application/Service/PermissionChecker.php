<?php

namespace App\Security\Application\Service;

use App\Security\Domain\Entity\Utilisateur;

/**
 * Évalue les droits dynamiques d'un utilisateur (matrice de son rôle).
 * Le super administrateur et le rôle système « Administrateur » ont tous les droits de leur périmètre.
 */
class PermissionChecker
{
    public function isSuperAdmin(Utilisateur $user): bool
    {
        return in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
    }

    public function isFullAccess(Utilisateur $user): bool
    {
        return $this->isSuperAdmin($user) || ($user->getRole()?->isSysteme() ?? false);
    }

    public function can(Utilisateur $user, string $module, string $letter = 'R'): bool
    {
        if ($this->isFullAccess($user)) {
            return true;
        }
        $role = $user->getRole();
        if (!$role || !$role->isActive()) {
            return false;
        }
        $letters = $role->getPermissions()[$module] ?? '';

        return $letters !== '' && str_contains($letters, strtoupper($letter));
    }

    public function hasAction(Utilisateur $user, string $action): bool
    {
        if ($this->isFullAccess($user)) {
            return true;
        }
        $role = $user->getRole();

        return $role !== null && $role->isActive() && in_array($action, $role->getActions(), true);
    }

    /** Matrice effective renvoyée au front. */
    public function effective(Utilisateur $user): array
    {
        if ($this->isFullAccess($user)) {
            $perms = [];
            foreach (\App\Security\Domain\PermissionCatalog::modules() as $code => $m) {
                $perms[$code] = implode('', array_values(array_intersect(['C', 'R', 'U', 'D'], $m['letters'])));
            }
            return ['full' => true, 'permissions' => $perms, 'actions' => \App\Security\Domain\PermissionCatalog::actions()];
        }
        $role = $user->getRole();

        return ['full' => false, 'permissions' => $role?->getPermissions() ?? [], 'actions' => $role?->getActions() ?? []];
    }

    /** Menu de navigation : groupes et modules que l'utilisateur peut au moins lire. */
    public function menu(Utilisateur $user): array
    {
        $menu = [];
        foreach (\App\Security\Domain\PermissionCatalog::groupes() as $groupe) {
            $modules = [];
            foreach ($groupe['modules'] as $m) {
                if ($this->can($user, $m['code'], 'R')) {
                    $modules[] = ['code' => $m['code'], 'titre' => $m['titre'], 'lien' => $m['lien'], 'icon' => $m['icon']];
                }
            }
            if ($modules) {
                $menu[] = ['code' => $groupe['code'], 'titre' => $groupe['titre'], 'icon' => $groupe['icon'], 'modules' => $modules];
            }
        }

        return $menu;
    }
}

<?php

namespace App\Security\Application\Service;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Shared\Application\Service\TenantContext;
use App\Security\Domain\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Représentation de l'utilisateur renvoyée au front après login / sur /auth/me,
 * avec ses droits effectifs (matrice du rôle) et ses centres accessibles.
 */
class SessionPayloadFactory
{
    public function __construct(private EntityManagerInterface $em, private PermissionChecker $checker, private TenantContext $tenant) {}

    /** Le super administrateur « entre » dans l'entreprise choisie (header X-Entreprise-Id). */
    private function entrepriseOf(Utilisateur $user): ?Entreprise
    {
        return $this->checker->isSuperAdmin($user) ? ($this->tenant->getEntreprise() ?? $user->getEntreprise()) : $user->getEntreprise();
    }

    /** @return Centre[] */
    public function accessibleCentres(Utilisateur $user): array
    {
        $entreprise = $this->entrepriseOf($user);
        if (!$entreprise) {
            return [];
        }
        $own = $user->getCentres()->filter(fn (Centre $c) => $c->isActive())->toArray();
        if ($own && !$this->checker->isSuperAdmin($user)) {
            return array_values($own);
        }

        return $this->em->getRepository(Centre::class)->findBy(['entreprise' => $entreprise, 'isActive' => true], ['id' => 'ASC']);
    }

    public function build(Utilisateur $user): array
    {
        $entreprise = $this->entrepriseOf($user);
        $centres = array_map(fn (Centre $c) => ['id' => $c->getId(), 'nom' => $c->getNom(), 'code' => $c->getCode()], $this->accessibleCentres($user));
        $role = $user->getRole();

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'telephone' => $user->getTelephone(),
            'is_super_admin' => $this->checker->isSuperAdmin($user),
            'role' => $role ? ['id' => $role->getId(), 'code' => $role->getCode(), 'nom' => $role->getNom(), 'systeme' => $role->isSysteme()] : null,
            'access' => $this->checker->effective($user) + ['menu' => $this->checker->menu($user)],
            'entreprise' => $entreprise ? [
                'id' => $entreprise->getId(),
                'nom' => $entreprise->getNom(),
                'code' => $entreprise->getCode(),
                'devise' => $entreprise->getDevise(),
                'plan' => $entreprise->getPlan(),
                'dateExpiration' => $entreprise->getDateExpiration()?->format('Y-m-d'),
            ] : null,
            'centre' => $centres[0] ?? null,
            'centres' => $centres,
            'service' => $user->getService() ? ['id' => $user->getService()->getId(), 'nom' => $user->getService()->getNom()] : null,
            'login_count' => $user->getLoginCount(),
            'must_change_password' => $user->isMustChangePassword(),
        ];
    }
}

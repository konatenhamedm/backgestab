<?php

namespace App\Security\Application\Service;

use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Domain\Entity\Role;
use App\Security\Domain\PermissionCatalog;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Initialise l'espace d'une nouvelle entreprise : rôle système « Administrateur » (verrouillé)
 * et rôles par défaut modifiables.
 */
class EntrepriseProvisioner
{
    public const PLANS = [
        'ESSAI' => ['label' => 'Essai gratuit (30 jours)', 'maxCentres' => 3, 'maxUtilisateurs' => 20, 'jours' => 30],
        'STARTER' => ['label' => 'Starter', 'maxCentres' => 1, 'maxUtilisateurs' => 10, 'jours' => null],
        'PRO' => ['label' => 'Pro', 'maxCentres' => 5, 'maxUtilisateurs' => 50, 'jours' => null],
        'ENTREPRISE' => ['label' => 'Entreprise', 'maxCentres' => 100, 'maxUtilisateurs' => 1000, 'jours' => null],
    ];

    public function __construct(private EntityManagerInterface $em) {}

    public function appliquerPlan(Entreprise $entreprise, string $plan): void
    {
        $def = self::PLANS[$plan] ?? self::PLANS['ESSAI'];
        $entreprise->setPlan(isset(self::PLANS[$plan]) ? $plan : 'ESSAI')
            ->setMaxCentres($def['maxCentres'])
            ->setMaxUtilisateurs($def['maxUtilisateurs']);
        if ($def['jours'] !== null) {
            $entreprise->setDateExpiration(new \DateTimeImmutable(sprintf('+%d days', $def['jours'])));
        }
    }

    /** @return array{admin: Role, roles: Role[]} */
    public function provisionRoles(Entreprise $entreprise): array
    {
        $admin = (new Role())->setEntreprise($entreprise)->setCode('ADMIN')->setNom('Administrateur')
            ->setDescription('Accès complet à l\'espace de l\'entreprise (non modifiable).')
            ->setSysteme(true);
        $this->em->persist($admin);

        $roles = [];
        foreach (PermissionCatalog::rolesParDefaut() as $def) {
            $role = (new Role())->setEntreprise($entreprise)->setCode($def['code'])->setNom($def['nom'])->setDescription($def['description'])
                ->setPermissions(PermissionCatalog::sanitizePermissions($def['permissions']))
                ->setActions(PermissionCatalog::sanitizeActions($def['actions']));
            $this->em->persist($role);
            $roles[$def['code']] = $role;
        }

        return ['admin' => $admin, 'roles' => $roles];
    }
}

<?php

namespace App\Security\Domain\Attribute;

/**
 * Exige un droit dynamique (rôle de l'entreprise) pour appeler une action de contrôleur.
 *
 *   #[RequirePermission('articles', 'C')]                          → création d'article
 *   #[RequirePermission(action: 'demandes.valider')]               → action spéciale
 *   #[RequirePermission('articles', 'R', or: [['demandes', 'C']])] → au moins une des conditions
 *   #[RequirePermission(superAdmin: true)]                         → réservé au super administrateur
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class RequirePermission
{
    /**
     * @param list<array{0:string,1:string}> $or alternatives [module, lettre]
     */
    public function __construct(
        public readonly ?string $module = null,
        public readonly string $letter = 'R',
        public readonly ?string $action = null,
        public readonly array $or = [],
        public readonly bool $superAdmin = false,
    ) {}
}

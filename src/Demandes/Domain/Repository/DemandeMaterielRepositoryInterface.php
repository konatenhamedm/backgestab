<?php

namespace App\Demandes\Domain\Repository;

use App\Demandes\Domain\Entity\DemandeMateriel;
use App\Organisation\Domain\Entity\Centre;
use App\Security\Domain\Entity\Utilisateur;

interface DemandeMaterielRepositoryInterface
{
    public function findOneInCentre(int $id, Centre $centre): ?DemandeMateriel;

    /** @return DemandeMateriel[] */
    public function search(Centre $centre, ?Utilisateur $demandeur = null, ?string $statut = null): array;

    public function countByStatut(Centre $centre, string $statut): int;

    public function add(DemandeMateriel $demande, bool $flush = false): void;
}

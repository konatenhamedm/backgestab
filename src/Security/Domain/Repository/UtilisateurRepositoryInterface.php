<?php

namespace App\Security\Domain\Repository;

use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Domain\Entity\Utilisateur;

interface UtilisateurRepositoryInterface
{
    public function findByEmail(string $email): ?Utilisateur;

    /** @return Utilisateur[] */
    public function findByEntreprise(Entreprise $entreprise, ?string $role = null): array;

    public function add(Utilisateur $utilisateur, bool $flush = false): void;
}

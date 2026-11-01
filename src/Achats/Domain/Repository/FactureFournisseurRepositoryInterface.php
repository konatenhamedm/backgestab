<?php

namespace App\Achats\Domain\Repository;

use App\Achats\Domain\Entity\FactureFournisseur;
use App\Organisation\Domain\Entity\Entreprise;

interface FactureFournisseurRepositoryInterface
{
    public function findOneInEntreprise(int $id, Entreprise $entreprise): ?FactureFournisseur;

    /** @return FactureFournisseur[] */
    public function search(Entreprise $entreprise, ?string $statut = null, ?int $fournisseurId = null): array;

    /** @return FactureFournisseur[] Factures non soldées, triées par échéance */
    public function findEnAttente(Entreprise $entreprise): array;

    public function add(FactureFournisseur $facture, bool $flush = false): void;
}

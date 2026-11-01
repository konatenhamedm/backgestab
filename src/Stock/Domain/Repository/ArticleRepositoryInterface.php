<?php

namespace App\Stock\Domain\Repository;

use App\Organisation\Domain\Entity\Centre;
use App\Stock\Domain\Entity\Article;

interface ArticleRepositoryInterface
{
    public function findOneInCentre(int $id, Centre $centre): ?Article;

    /** @return Article[] */
    public function search(Centre $centre, ?string $search = null, ?int $categorieId = null, ?string $statut = null): array;

    /** @return Article[] Articles dont le stock est <= au seuil d'alerte */
    public function findSousSeuil(Centre $centre): array;

    public function countActifs(Centre $centre): int;

    public function add(Article $article, bool $flush = false): void;
}

<?php

namespace App\Shared\Application\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Génère des numéros de documents lisibles : PREFIXE-ANNEE-0001 (séquence par entreprise et par an).
 */
class NumeroGenerator
{
    public function __construct(private EntityManagerInterface $em) {}

    /**
     * @param class-string $entityClass entité portant les champs `numeroField` et `entreprise`
     */
    public function next(string $prefix, string $entityClass, string $numeroField, int $entrepriseId): string
    {
        $year = (new \DateTimeImmutable())->format('Y');
        $like = sprintf('%s-%s-%%', $prefix, $year);

        $last = $this->em->createQueryBuilder()
            ->select(sprintf('MAX(e.%s)', $numeroField))
            ->from($entityClass, 'e')
            ->where(sprintf('e.%s LIKE :like', $numeroField))
            ->andWhere('IDENTITY(e.entreprise) = :ent')
            ->setParameter('like', $like)
            ->setParameter('ent', $entrepriseId)
            ->getQuery()->getSingleScalarResult();

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }
}

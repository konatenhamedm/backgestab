<?php

namespace App\Stock\Infrastructure\Persistence;

use App\Organisation\Domain\Entity\Centre;
use App\Stock\Domain\Entity\Article;
use App\Stock\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findOneInCentre(int $id, Centre $centre): ?Article
    {
        return $this->findOneBy(['id' => $id, 'centre' => $centre, 'isActive' => true]);
    }

    public function search(Centre $centre, ?string $search = null, ?int $categorieId = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.categorie', 'c')->addSelect('c')
            ->where('a.centre = :centre')->andWhere('a.isActive = true')
            ->setParameter('centre', $centre)
            ->orderBy('a.designation', 'ASC');

        if ($search) {
            $qb->andWhere('LOWER(a.designation) LIKE :s OR LOWER(a.codeSku) LIKE :s')
               ->setParameter('s', '%' . mb_strtolower($search) . '%');
        }
        if ($categorieId) {
            $qb->andWhere('a.categorie = :cat')->setParameter('cat', $categorieId);
        }
        if ($statut === 'rupture') {
            $qb->andWhere('a.quantiteStock <= 0');
        } elseif ($statut === 'alerte') {
            $qb->andWhere('a.quantiteStock > 0 AND a.quantiteStock <= a.seuilAlerteMin');
        } elseif ($statut === 'ok') {
            $qb->andWhere('a.quantiteStock > a.seuilAlerteMin');
        }

        return $qb->getQuery()->getResult();
    }

    public function findSousSeuil(Centre $centre): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.centre = :centre')->andWhere('a.isActive = true')
            ->andWhere('a.quantiteStock <= a.seuilAlerteMin')
            ->setParameter('centre', $centre)
            ->orderBy('a.designation', 'ASC')
            ->getQuery()->getResult();
    }

    public function countActifs(Centre $centre): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.centre = :centre')->andWhere('a.isActive = true')
            ->setParameter('centre', $centre)
            ->getQuery()->getSingleScalarResult();
    }

    public function add(Article $article, bool $flush = false): void
    {
        $this->getEntityManager()->persist($article);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

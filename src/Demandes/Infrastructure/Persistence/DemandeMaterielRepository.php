<?php

namespace App\Demandes\Infrastructure\Persistence;

use App\Demandes\Domain\Entity\DemandeMateriel;
use App\Demandes\Domain\Repository\DemandeMaterielRepositoryInterface;
use App\Organisation\Domain\Entity\Centre;
use App\Security\Domain\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DemandeMateriel>
 */
class DemandeMaterielRepository extends ServiceEntityRepository implements DemandeMaterielRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeMateriel::class);
    }

    public function findOneInCentre(int $id, Centre $centre): ?DemandeMateriel
    {
        return $this->findOneBy(['id' => $id, 'centre' => $centre]);
    }

    public function search(Centre $centre, ?Utilisateur $demandeur = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->join('d.demandeur', 'u')->addSelect('u')
            ->leftJoin('d.service', 's')->addSelect('s')
            ->leftJoin('d.lignes', 'l')->addSelect('l')
            ->leftJoin('l.article', 'a')->addSelect('a')
            ->where('d.centre = :centre')->setParameter('centre', $centre)
            ->orderBy('d.dateDemande', 'DESC')->addOrderBy('d.id', 'DESC');

        if ($demandeur) {
            $qb->andWhere('d.demandeur = :u')->setParameter('u', $demandeur);
        }
        if ($statut) {
            $qb->andWhere('d.statut = :st')->setParameter('st', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByStatut(Centre $centre, string $statut): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.centre = :c')->andWhere('d.statut = :s')
            ->setParameter('c', $centre)->setParameter('s', $statut)
            ->getQuery()->getSingleScalarResult();
    }

    public function add(DemandeMateriel $demande, bool $flush = false): void
    {
        $this->getEntityManager()->persist($demande);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

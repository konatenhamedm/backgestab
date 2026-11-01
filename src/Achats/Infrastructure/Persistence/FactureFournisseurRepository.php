<?php

namespace App\Achats\Infrastructure\Persistence;

use App\Achats\Domain\Entity\FactureFournisseur;
use App\Achats\Domain\Repository\FactureFournisseurRepositoryInterface;
use App\Organisation\Domain\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FactureFournisseur>
 */
class FactureFournisseurRepository extends ServiceEntityRepository implements FactureFournisseurRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FactureFournisseur::class);
    }

    public function findOneInEntreprise(int $id, Entreprise $entreprise): ?FactureFournisseur
    {
        return $this->findOneBy(['id' => $id, 'entreprise' => $entreprise]);
    }

    public function search(Entreprise $entreprise, ?string $statut = null, ?int $fournisseurId = null): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.fournisseur', 'fo')->addSelect('fo')
            ->where('f.entreprise = :e')->setParameter('e', $entreprise)
            ->orderBy('f.dateFacture', 'DESC')->addOrderBy('f.id', 'DESC');

        if ($statut) {
            $qb->andWhere('f.statutPaiement = :s')->setParameter('s', $statut);
        }
        if ($fournisseurId) {
            $qb->andWhere('fo.id = :fo')->setParameter('fo', $fournisseurId);
        }

        return $qb->getQuery()->getResult();
    }

    public function findEnAttente(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.fournisseur', 'fo')->addSelect('fo')
            ->where('f.entreprise = :e')->andWhere('f.statutPaiement <> :paye')
            ->setParameter('e', $entreprise)->setParameter('paye', FactureFournisseur::PAYEE)
            ->orderBy('f.dateEcheance', 'ASC')->addOrderBy('f.dateFacture', 'ASC')
            ->getQuery()->getResult();
    }

    public function add(FactureFournisseur $facture, bool $flush = false): void
    {
        $this->getEntityManager()->persist($facture);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

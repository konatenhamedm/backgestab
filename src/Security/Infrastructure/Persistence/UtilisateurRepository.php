<?php

namespace App\Security\Infrastructure\Persistence;

use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements UtilisateurRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function findByEmail(string $email): ?Utilisateur
    {
        return $this->findOneBy(['email' => mb_strtolower(trim($email))]);
    }

    public function findByEntreprise(Entreprise $entreprise, ?string $role = null): array
    {
        $users = $this->createQueryBuilder('u')
            ->where('u.entreprise = :e')
            ->setParameter('e', $entreprise)
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC')
            ->getQuery()->getResult();

        return $role === null ? $users : array_values(array_filter($users, fn (Utilisateur $u) => in_array($role, $u->getRoles(), true)));
    }

    public function add(Utilisateur $utilisateur, bool $flush = false): void
    {
        $this->getEntityManager()->persist($utilisateur);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}

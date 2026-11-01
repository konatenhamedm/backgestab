<?php

namespace App\Security\UI\Controller;

use App\Security\Domain\Attribute\RequirePermission;
use App\Security\Domain\Entity\JournalActivite;
use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/security/journal')]
class ApiJournalController extends ApiInterface
{
    #[RequirePermission('journal', 'R')]
    #[Route('', name: 'api_security_journal', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, EntityManagerInterface $em): JsonResponse
    {
        $qb = $em->createQueryBuilder()
            ->select('j', 'u', 'c')->from(JournalActivite::class, 'j')
            ->leftJoin('j.utilisateur', 'u')->leftJoin('j.centre', 'c')
            ->where('j.entreprise = :e')->setParameter('e', $tenant->requireEntreprise())
            ->orderBy('j.id', 'DESC')
            ->setMaxResults(min(500, $request->query->getInt('limit', 200)));

        if ($user = $request->query->getInt('utilisateur')) {
            $qb->andWhere('u.id = :u')->setParameter('u', $user);
        }
        if ($methode = $request->query->get('methode')) {
            $qb->andWhere('j.methode = :m')->setParameter('m', strtoupper($methode));
        }

        return $this->responseData(array_map(fn (JournalActivite $j) => [
            'id' => $j->getId(),
            'date' => $j->getCreatedAt()?->format('c'),
            'utilisateur' => $j->getUtilisateur()?->getNomComplet(),
            'email' => $j->getUtilisateur()?->getEmail(),
            'centre' => $j->getCentre()?->getNom(),
            'methode' => $j->getMethode(),
            'chemin' => $j->getChemin(),
            'route' => $j->getRoute(),
            'statut' => $j->getStatut(),
            'ip' => $j->getIp(),
        ], $qb->getQuery()->getResult()));
    }
}

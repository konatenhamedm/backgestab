<?php

namespace App\Security\Infrastructure\EventSubscriber;

use App\Security\Domain\Entity\JournalActivite;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Journal d'activité : trace toute action d'écriture (POST/PUT/PATCH/DELETE) de l'API,
 * par entreprise et par centre. Aucun contenu de requête n'est conservé (pas de mots de passe).
 */
class JournalActiviteSubscriber implements EventSubscriberInterface
{
    private const IGNORED = ['/api/auth/logout'];

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private TenantContext $tenant
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::TERMINATE => 'onTerminate'];
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api') || in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true) || in_array($path, self::IGNORED, true)) {
            return;
        }

        $user = $this->security->getUser();
        $isLogin = $path === '/api/login';
        if (!$user instanceof Utilisateur && !$isLogin) {
            return;
        }

        try {
            if (!$this->em->isOpen()) {
                return;
            }
            $log = (new JournalActivite())
                ->setEntreprise($user instanceof Utilisateur ? $this->tenant->getEntreprise() ?? $user->getEntreprise() : null)
                ->setUtilisateur($user instanceof Utilisateur ? $this->em->getReference(Utilisateur::class, $user->getId()) : null)
                ->setCentre($this->tenant->getCentre() ? $this->em->getReference(\App\Organisation\Domain\Entity\Centre::class, $this->tenant->getCentre()->getId()) : null)
                ->setMethode($request->getMethod())
                ->setChemin(mb_substr($path, 0, 255))
                ->setRoute($request->attributes->get('_route'))
                ->setStatut($event->getResponse()->getStatusCode())
                ->setIp($request->getClientIp());
            $this->em->persist($log);
            $this->em->flush();
        } catch (\Throwable) {
            // La journalisation ne doit jamais casser une requête
        }
    }
}

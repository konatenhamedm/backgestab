<?php

namespace App\Shared\UI\EventSubscriber;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Domain\Entity\Utilisateur;
use App\Shared\Application\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Résout l'entreprise et le centre actifs :
 *  - utilisateur standard : son entreprise, et son centre (ou X-Centre-Id s'il appartient à l'entreprise)
 *  - super admin : headers X-Entreprise-Id / X-Centre-Id
 */
class TenantContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private TenantContext $context,
        private EntityManagerInterface $em
    ) {}

    public static function getSubscribedEvents(): array
    {
        // Après le firewall (priorité 8)
        return [KernelEvents::REQUEST => ['onKernelRequest', 0]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        if ($request->isMethod('OPTIONS')) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            return;
        }

        $isSuper = in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true);
        $entreprise = $user->getEntreprise();

        if ($isSuper && ($id = $request->headers->get('X-Entreprise-Id'))) {
            $entreprise = $this->em->getRepository(Entreprise::class)->find((int) $id) ?? $entreprise;
        }
        $this->context->setEntreprise($entreprise);

        if (!$entreprise) {
            return;
        }

        // Centres accessibles : ceux de l'utilisateur, ou tous les centres actifs de l'entreprise
        $allowed = $user->getCentres()->filter(fn (Centre $c) => $c->isActive())->toArray();
        if ($isSuper || !$allowed) {
            $allowed = $this->em->getRepository(Centre::class)->findBy(['entreprise' => $entreprise, 'isActive' => true], ['id' => 'ASC']);
        }

        $centre = null;
        if ($centreId = $request->headers->get('X-Centre-Id')) {
            foreach ($allowed as $candidate) {
                if ($candidate->getId() === (int) $centreId) {
                    $centre = $candidate;
                    break;
                }
            }
        }
        $centre ??= $allowed[0] ?? null;
        $this->context->setCentre($centre);
    }
}

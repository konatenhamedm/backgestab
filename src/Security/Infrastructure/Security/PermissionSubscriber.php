<?php

namespace App\Security\Infrastructure\Security;

use App\Security\Application\Service\PermissionChecker;
use App\Security\Domain\Attribute\RequirePermission;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Applique les attributs #[RequirePermission] (classe et méthode) avant l'exécution du contrôleur.
 */
class PermissionSubscriber implements EventSubscriberInterface
{
    public function __construct(private Security $security, private PermissionChecker $checker) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER_ARGUMENTS => 'onController'];
    }

    public function onController(ControllerArgumentsEvent $event): void
    {
        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        $reflection = new \ReflectionMethod($controller[0], $controller[1]);
        $attributes = array_merge(
            $reflection->getDeclaringClass()->getAttributes(RequirePermission::class),
            $reflection->getAttributes(RequirePermission::class)
        );
        if (!$attributes) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            throw new AccessDeniedException('Authentification requise.');
        }

        // Plusieurs attributs = ET logique ; au sein d'un attribut, `or` = OU logique
        foreach ($attributes as $attribute) {
            /** @var RequirePermission $rule */
            $rule = $attribute->newInstance();
            if ($rule->superAdmin) {
                if (!$this->checker->isSuperAdmin($user)) {
                    throw new AccessDeniedException('Réservé à l\'administration de la plateforme.');
                }
                continue;
            }

            $granted = $this->evaluate($user, $rule->module, $rule->letter, $rule->action);
            foreach ($rule->or as [$module, $letter]) {
                $granted = $granted || $this->checker->can($user, $module, $letter);
            }
            if (!$granted) {
                throw new AccessDeniedException('Permission insuffisante.');
            }
        }
    }

    private function evaluate(Utilisateur $user, ?string $module, string $letter, ?string $action): bool
    {
        if ($action !== null) {
            return $this->checker->hasAction($user, $action);
        }

        return $module !== null && $this->checker->can($user, $module, $letter);
    }
}

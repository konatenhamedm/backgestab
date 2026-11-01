<?php

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Vérifie le code applicatif (X-APP-SECURITY-CODE) envoyé par le front sur toute l'API.
 */
class ApiSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(#[Autowire('%app.security_code%')] private string $securityCode) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 64]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!$event->isMainRequest() || $request->isMethod('OPTIONS')
            || !str_starts_with($path, '/api') || str_starts_with($path, '/api/doc')) {
            return;
        }

        $provided = (string) $request->headers->get('X-APP-SECURITY-CODE', '');
        if ($this->securityCode === '' || !hash_equals($this->securityCode, $provided)) {
            $event->setResponse(new JsonResponse([
                'code' => 403,
                'message' => 'Accès refusé : code applicatif invalide.',
                'errors' => ['Accès refusé : code applicatif invalide.'],
            ], 403));
        }
    }
}

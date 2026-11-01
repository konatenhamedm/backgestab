<?php

namespace App\Shared\Infrastructure\EventSubscriber;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Toute erreur sur /api est renvoyée dans l'enveloppe JSON { code, message, data, errors }.
 */
class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(#[Autowire('%kernel.debug%')] private bool $debug) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => ['onException', 0]];
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api') || str_starts_with($request->getPathInfo(), '/api/doc')) {
            return;
        }

        $e = $event->getThrowable();

        [$status, $message] = match (true) {
            $e instanceof AuthenticationException => [401, 'Authentification requise.'],
            $e instanceof AccessDeniedException => [403, "Vous n'avez pas les permissions nécessaires pour cette action."],
            $e instanceof \DomainException => [400, $e->getMessage()],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), match ($e->getStatusCode()) {
                403 => "Vous n'avez pas les permissions nécessaires pour cette action.",
                404 => 'Ressource introuvable.',
                default => $e->getMessage() ?: 'Erreur.',
            }],
            default => [500, $this->debug ? $e->getMessage() : 'Une erreur serveur est survenue.'],
        };

        $event->setResponse(new JsonResponse(['code' => $status, 'message' => $message, 'data' => null, 'errors' => [$message]], $status));
    }
}

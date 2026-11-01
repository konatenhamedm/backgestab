<?php

namespace App\Shared\UI\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Security\Application\Service\PermissionChecker;
use App\Security\Domain\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Base des contrôleurs API : enveloppe de réponse commune { code, message, data, errors, meta? }.
 */
abstract class ApiInterface extends AbstractController
{
    protected int $statusCode = 200;
    protected string $message = 'Opération effectuée avec succès';

    public function __construct(
        protected ValidatorInterface $validator,
        protected SerializerInterface $serializer
    ) {}

    protected PermissionChecker $permissions;

    #[Required]
    public function setPermissionChecker(PermissionChecker $permissions): void
    {
        $this->permissions = $permissions;
    }

    protected function currentUser(): Utilisateur
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }

    /** Droit dynamique (matrice du rôle) de l'utilisateur courant. */
    protected function can(string $module, string $letter = 'R'): bool
    {
        return $this->permissions->can($this->currentUser(), $module, $letter);
    }

    protected function canDo(string $action): bool
    {
        return $this->permissions->hasAction($this->currentUser(), $action);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    protected function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    protected function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    protected function body(Request $request): array
    {
        $data = json_decode($request->getContent(), true);
        return is_array($data) ? $data : [];
    }

    public function responseData($data = [], array $headers = [], array $meta = [], array $groups = []): JsonResponse
    {
        $context = $groups ? ['groups' => $groups] : [];

        $serialized = $data;
        if (is_object($data) || (is_array($data) && $data !== [] && is_object(reset($data)))) {
            $serialized = json_decode($this->serializer->serialize($data, 'json', $context), true);
        }

        $payload = [
            'code' => $this->statusCode,
            'message' => $this->message,
            'data' => $serialized,
            'errors' => [],
        ];
        if ($meta) {
            $payload['meta'] = $meta;
        }

        $status = $this->statusCode;
        $this->statusCode = 200;

        return new JsonResponse($payload, $status, $headers);
    }

    public function errorResponse($DTO = null, string $customMessage = '', int $statusCode = 400): JsonResponse
    {
        $errors = [];
        if ($DTO !== null) {
            foreach ($this->validator->validate($DTO) as $error) {
                $errors[] = $error->getMessage();
            }
        }
        if ($customMessage !== '') {
            $errors[] = $customMessage;
        }

        return new JsonResponse([
            'code' => $statusCode,
            'message' => $customMessage ?: 'Validation failed',
            'data' => null,
            'errors' => $errors,
        ], $statusCode);
    }
}

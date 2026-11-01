<?php

namespace App\Security\UI\Controller;

use App\Security\Application\DTO\LoginDTO;
use App\Security\Application\Service\SessionPayloadFactory;
use App\Security\Application\UseCase\LoginUseCase;
use App\Security\Application\UseCase\RegisterEntrepriseUseCase;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Infrastructure\Security\JwtService;
use App\Shared\UI\Controller\ApiInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends ApiInterface
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, LoginUseCase $useCase, JwtService $jwt, SessionPayloadFactory $session): JsonResponse
    {
        $data = $this->body($request);
        if (empty($data['email']) || empty($data['password'])) {
            return $this->errorResponse(null, 'Email et mot de passe requis.', 400);
        }

        try {
            $result = $useCase->execute(new LoginDTO($data['email'], $data['password'], $request->getClientIp()));
        } catch (\DomainException $e) {
            return $this->errorResponse(null, $e->getMessage(), $e->getCode() ?: 401);
        }

        return $this->responseData([
            'token' => $result['token'],
            'user' => $session->build($result['user']),
            'must_change_password' => $result['user']->isMustChangePassword(),
            'token_expires_in' => $jwt->getTtl(),
        ]);
    }

    #[Route('/api/auth/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request, RegisterEntrepriseUseCase $useCase, LoginUseCase $login, SessionPayloadFactory $session, JwtService $jwt): JsonResponse
    {
        try {
            $admin = $useCase->execute($this->body($request));
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->setStatusCode(201)->setMessage('Entreprise créée avec succès.')->responseData([
            'token' => $login->issueToken($admin),
            'user' => $session->build($admin),
            'must_change_password' => false,
            'token_expires_in' => $jwt->getTtl(),
        ]);
    }

    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(SessionPayloadFactory $session): JsonResponse
    {
        $user = $this->getUser();
        return $user instanceof Utilisateur
            ? $this->responseData($session->build($user))
            : $this->errorResponse(null, 'Authentification requise.', 401);
    }

    #[Route('/api/auth/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function changePassword(Request $request, UserPasswordHasherInterface $hasher, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->errorResponse(null, 'Authentification requise.', 401);
        }

        $data = $this->body($request);
        $new = (string) ($data['new_password'] ?? '');
        if (strlen($new) < 8) {
            return $this->errorResponse(null, 'Le nouveau mot de passe doit contenir au moins 8 caractères.', 400);
        }
        if (!$user->isMustChangePassword() && !$hasher->isPasswordValid($user, (string) ($data['current_password'] ?? ''))) {
            return $this->errorResponse(null, 'Mot de passe actuel incorrect.', 400);
        }

        $user->setPassword($hasher->hashPassword($user, $new));
        $user->setMustChangePassword(false);
        $em->flush();

        return $this->setMessage('Mot de passe modifié.')->responseData([]);
    }

    #[Route('/api/auth/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request, JwtService $jwt): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (preg_match('/Bearer\s+(.*)$/i', $header, $m)) {
            $jwt->invalidateToken($m[1]);
        }

        return $this->setMessage('Déconnexion effectuée.')->responseData([]);
    }
}

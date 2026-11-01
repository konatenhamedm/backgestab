<?php

namespace App\Security\Application\UseCase;

use App\Security\Application\DTO\LoginDTO;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use App\Security\Infrastructure\Security\JwtService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginUseCase
{
    public function __construct(
        private UtilisateurRepositoryInterface $users,
        private UserPasswordHasherInterface $hasher,
        private JwtService $jwt,
        private EntityManagerInterface $em
    ) {}

    /** @return array{user: Utilisateur, token: string} */
    public function execute(LoginDTO $dto): array
    {
        $user = $this->users->findByEmail($dto->email);

        if (!$user || !$this->hasher->isPasswordValid($user, $dto->password)) {
            throw new \DomainException('Email ou mot de passe incorrect.', 401);
        }
        if (!$user->isActive()) {
            throw new \DomainException('Votre compte a été désactivé.', 403);
        }
        $entreprise = $user->getEntreprise();
        if ($entreprise && !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            if (!$entreprise->isActive()) {
                throw new \DomainException("L'accès de votre entreprise est suspendu. Contactez l'administrateur de la plateforme.", 403);
            }
            if ($entreprise->isExpire()) {
                throw new \DomainException("L'abonnement de votre entreprise a expiré. Contactez l'administrateur de la plateforme.", 403);
            }
        }

        $user->incrementLoginCount();
        $this->em->flush();

        return ['user' => $user, 'token' => $this->issueToken($user)];
    }

    public function issueToken(Utilisateur $user): string
    {
        return $this->jwt->generateToken([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'role_id' => $user->getRole()?->getId(),
            'entreprise_id' => $user->getEntreprise()?->getId(),
        ]);
    }
}

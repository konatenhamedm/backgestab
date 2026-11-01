<?php

namespace App\Security\Application\UseCase;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Security\Application\Service\EntrepriseProvisioner;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\Repository\UtilisateurRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Inscription SaaS : crée une entreprise, son centre principal et son administrateur.
 */
class RegisterEntrepriseUseCase
{
    public function __construct(
        private EntityManagerInterface $em,
        private UtilisateurRepositoryInterface $users,
        private UserPasswordHasherInterface $hasher,
        private EntrepriseProvisioner $provisioner
    ) {}

    public function execute(array $data, ?string $plan = null): Utilisateur
    {
        foreach (['entreprise', 'email', 'password', 'nom', 'prenom'] as $field) {
            if (empty(trim((string) ($data[$field] ?? '')))) {
                throw new \InvalidArgumentException(sprintf('Le champ "%s" est obligatoire.', $field));
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("L'adresse email est invalide.");
        }
        if (strlen($data['password']) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères.');
        }
        if ($this->users->findByEmail($data['email'])) {
            throw new \InvalidArgumentException('Cette adresse email est déjà utilisée.');
        }

        return $this->em->wrapInTransaction(function () use ($data) {
            $entreprise = (new Entreprise())
                ->setNom(trim($data['entreprise']))
                ->setCode($this->uniqueCode($data['entreprise']))
                ->setEmail($data['email'])
                ->setTelephone($data['telephone'] ?? null);
            $this->provisioner->appliquerPlan($entreprise, $plan ?? 'ESSAI');
            $this->em->persist($entreprise);
            $roles = $this->provisioner->provisionRoles($entreprise);

            $centre = (new Centre())
                ->setEntreprise($entreprise)
                ->setNom($data['centre'] ?? 'Centre principal')
                ->setCode('PRINC');
            $this->em->persist($centre);

            $admin = (new Utilisateur())
                ->setEmail($data['email'])
                ->setNom($data['nom'])
                ->setPrenom($data['prenom'])
                ->setTelephone($data['telephone'] ?? null)
                ->setRole($roles['admin'])
                ->setEntreprise($entreprise);
            $admin->setPassword($this->hasher->hashPassword($admin, $data['password']));
            $this->em->persist($admin);
            $this->em->flush();

            return $admin;
        });
    }

    private function uniqueCode(string $nom): string
    {
        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT', $nom) ?: $nom), 0, 8)) ?: 'ENT';
        $code = $base;
        $i = 1;
        while ($this->em->getRepository(Entreprise::class)->findOneBy(['code' => $code])) {
            $code = $base . ++$i;
        }
        return $code;
    }
}

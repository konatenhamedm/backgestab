<?php

namespace App\Organisation\Domain\Entity;

use App\Shared\Domain\Trait\CommonFieldsTrait;
use App\Shared\Domain\Trait\IsActiveTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'gst_entreprise', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_gst_entreprise_code', columns: ['code'])])]
#[ORM\HasLifecycleCallbacks]
class Entreprise
{
    use CommonFieldsTrait;
    use IsActiveTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['entreprise:read', 'ref'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['entreprise:read', 'ref'])]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    #[Groups(['entreprise:read', 'ref'])]
    private ?string $code = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?string $adresse = null;

    #[ORM\Column(length: 10, options: ['default' => 'FCFA'])]
    #[Groups(['entreprise:read'])]
    private ?string $devise = 'FCFA';

    #[ORM\Column(length: 30, options: ['default' => 'ESSAI'])]
    #[Groups(['entreprise:read'])]
    private ?string $plan = 'ESSAI';

    #[ORM\Column()]
    #[Groups(['entreprise:read'])]
    private ?int $maxCentres = 3;

    #[ORM\Column()]
    #[Groups(['entreprise:read'])]
    private ?int $maxUtilisateurs = 20;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['entreprise:read'])]
    private ?\DateTimeImmutable $dateExpiration = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static
    {
        $this->adresse = $adresse;
        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(?string $devise): static
    {
        $this->devise = $devise;
        return $this;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(?string $plan): static
    {
        $this->plan = $plan;
        return $this;
    }

    public function getMaxCentres(): ?int
    {
        return $this->maxCentres;
    }

    public function setMaxCentres(?int $maxCentres): static
    {
        $this->maxCentres = $maxCentres;
        return $this;
    }

    public function getMaxUtilisateurs(): ?int
    {
        return $this->maxUtilisateurs;
    }

    public function setMaxUtilisateurs(?int $maxUtilisateurs): static
    {
        $this->maxUtilisateurs = $maxUtilisateurs;
        return $this;
    }

    public function getDateExpiration(): ?\DateTimeImmutable
    {
        return $this->dateExpiration;
    }

    public function setDateExpiration(?\DateTimeImmutable $dateExpiration): static
    {
        $this->dateExpiration = $dateExpiration;
        return $this;
    }

    public function isExpire(): bool
    {
        return $this->dateExpiration !== null && $this->dateExpiration < new \DateTimeImmutable('today');
    }

}

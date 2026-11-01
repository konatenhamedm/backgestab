<?php

namespace App\Shared\Application\Service;

use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;

/**
 * Contexte multi-entreprises (SaaS) de la requête courante : l'entreprise (tenant)
 * et le centre de gestion actif. Alimenté par TenantContextSubscriber.
 */
class TenantContext
{
    private ?Entreprise $entreprise = null;
    private ?Centre $centre = null;

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): void
    {
        $this->entreprise = $entreprise;
    }

    public function getCentre(): ?Centre
    {
        return $this->centre;
    }

    public function setCentre(?Centre $centre): void
    {
        $this->centre = $centre;
    }

    public function requireEntreprise(): Entreprise
    {
        return $this->entreprise ?? throw new \DomainException('Aucune entreprise active pour cette requête.');
    }

    public function requireCentre(): Centre
    {
        return $this->centre ?? throw new \DomainException('Veuillez sélectionner un centre de gestion.');
    }
}

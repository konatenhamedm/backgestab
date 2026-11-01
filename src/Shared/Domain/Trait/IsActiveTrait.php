<?php

namespace App\Shared\Domain\Trait;

use Doctrine\ORM\Mapping as ORM;

trait IsActiveTrait
{
    #[ORM\Column]
    private ?bool $isActive = true;

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function isIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }
}

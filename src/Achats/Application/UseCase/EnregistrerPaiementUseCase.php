<?php

namespace App\Achats\Application\UseCase;

use App\Achats\Domain\Entity\FactureFournisseur;
use App\Achats\Domain\Entity\PaiementFacture;
use Doctrine\ORM\EntityManagerInterface;

class EnregistrerPaiementUseCase
{
    public const MODES = ['VIREMENT', 'CHEQUE', 'ESPECES', 'MOBILE_MONEY', 'AUTRE'];

    public function __construct(private EntityManagerInterface $em) {}

    public function execute(FactureFournisseur $facture, array $data): PaiementFacture
    {
        $montant = number_format((float) ($data['montantRegle'] ?? 0), 2, '.', '');
        if (bccomp($montant, '0', 2) <= 0) {
            throw new \InvalidArgumentException('Le montant du règlement doit être positif.');
        }
        if (bccomp($montant, $facture->getResteAPayer(), 2) > 0) {
            throw new \InvalidArgumentException(sprintf('Le montant dépasse le reste à payer (%s).', $facture->getResteAPayer()));
        }
        $mode = strtoupper((string) ($data['modePaiement'] ?? 'VIREMENT'));
        if (!in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('Mode de paiement invalide.');
        }

        $paiement = (new PaiementFacture())
            ->setDatePaiement(new \DateTimeImmutable($data['datePaiement'] ?? 'today'))
            ->setMontantRegle($montant)
            ->setModePaiement($mode)
            ->setReference($data['reference'] ?? null);
        $facture->addPaiement($paiement);
        $facture->recalculerStatut();

        $this->em->persist($paiement);
        $this->em->flush();

        return $paiement;
    }
}

<?php

namespace App\Command;

use App\Achats\Application\UseCase\CreateBonReceptionUseCase;
use App\Achats\Application\UseCase\EnregistrerPaiementUseCase;
use App\Achats\Domain\Entity\Commande;
use App\Achats\Domain\Entity\FactureFournisseur;
use App\Achats\Domain\Entity\Fournisseur;
use App\Achats\Domain\Entity\LigneCommande;
use App\Demandes\Application\Service\DemandeWorkflow;
use App\Organisation\Domain\Entity\Centre;
use App\Organisation\Domain\Entity\Entreprise;
use App\Organisation\Domain\Entity\Service;
use App\Security\Application\Service\EntrepriseProvisioner;
use App\Security\Domain\Entity\Role;
use App\Security\Domain\Entity\Utilisateur;
use App\Security\Domain\PermissionCatalog;
use App\Shared\Application\Service\NumeroGenerator;
use App\Stock\Domain\Entity\Article;
use App\Stock\Domain\Entity\CategorieArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed-demo', description: 'Crée un super admin et deux entreprises de démonstration (centres, rôles, articles, demandes, factures...)')]
class SeedDemoDataCommand extends Command
{
    private string $password = 'Gestad@2026';
    private array $accounts = [];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private EntrepriseProvisioner $provisioner,
        private CreateBonReceptionUseCase $reception,
        private EnregistrerPaiementUseCase $paiement,
        private DemandeWorkflow $workflow,
        private NumeroGenerator $numeros
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe des comptes de démo', 'Gestad@2026');
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Vide toutes les tables GESTAD avant de recréer les données de démo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->password = $input->getOption('password');

        if ($input->getOption('reset')) {
            $this->em->getConnection()->executeStatement(
                'TRUNCATE gst_journal_activite, gst_paiement_facture, gst_facture_fournisseur, gst_ligne_bon_reception, gst_bon_reception, gst_ligne_commande, gst_commande, '
                . 'gst_bon_sortie, gst_ligne_demande, gst_demande_materiel, gst_mouvement_stock, gst_article, gst_categorie_article, gst_fournisseur, '
                . 'gst_utilisateur_centre, gst_utilisateur, gst_role, gst_service, gst_centre, gst_entreprise RESTART IDENTITY CASCADE'
            );
            $this->em->clear();
            $io->note('Tables vidées.');
        }

        if ($this->em->getRepository(Entreprise::class)->findOneBy(['code' => 'DEMO'])) {
            $io->warning('Les données de démo existent déjà (utilisez --reset pour tout recréer).');
            return Command::SUCCESS;
        }

        $this->account('Super administrateur (plateforme)', 'superadmin@gestad.app', 'Super', 'Admin', null, null, [], null, true);

        $this->seedInitialGroupe($io);
        $this->seedIvoireServices();

        $this->em->flush();
        $io->success('Données de démo créées.');
        $io->table(['Profil', 'Email', 'Mot de passe'], array_map(fn ($a) => [$a[0], $a[1], $this->password], $this->accounts));

        return Command::SUCCESS;
    }

    // ------------------------------------------------------------------ Entreprise 1 (complète, 2 centres)

    private function seedInitialGroupe(SymfonyStyle $io): void
    {
        $e = $this->entreprise('Initial Groupe', 'DEMO', 'contact@initial-groupe.example', 'PRO');
        $roles = $this->provisioner->provisionRoles($e);

        // Rôle personnalisé : démontre les permissions dynamiques par entreprise
        $responsable = (new Role())->setEntreprise($e)->setCode('RESP_SERVICE')->setNom('Responsable de service')
            ->setDescription('Voit et valide les demandes de son centre, sans toucher au stock ni aux achats.')
            ->setPermissions(PermissionCatalog::sanitizePermissions(['dashboard' => 'R', 'demandes' => 'CRD', 'articles' => 'R', 'rapports' => 'R']))
            ->setActions(PermissionCatalog::sanitizeActions(['dashboard.stats', 'demandes.voir_toutes', 'demandes.valider']));
        $this->em->persist($responsable);

        $abj = $this->centre($e, 'Siège Abidjan', 'ABJ');
        $bke = $this->centre($e, 'Agence Bouaké', 'BKE');
        $daf = $this->service($e, 'Direction administrative et financière', 'DAF');
        $rh = $this->service($e, 'Ressources humaines', 'RH');
        $it = $this->service($e, 'Informatique', 'IT');
        $log = $this->service($e, 'Logistique', 'LOG');

        $this->account('Administrateur', 'admin@demo.gestad.app', 'Awa', 'Koné', $e, $roles['admin'], [], $daf);
        $gest = $this->account('Gestionnaire de stock (Abidjan)', 'stock@demo.gestad.app', 'Moussa', 'Traoré', $e, $roles['roles']['GESTIONNAIRE'], [$abj], $log);
        $gestBke = $this->account('Gestionnaire de stock (Bouaké)', 'stock.bouake@demo.gestad.app', 'Issouf', 'Konaté', $e, $roles['roles']['GESTIONNAIRE'], [$bke], $log);
        $this->account('Comptable', 'compta@demo.gestad.app', 'Fatou', 'Diallo', $e, $roles['roles']['COMPTABLE'], [$abj, $bke], $daf);
        $this->account('Responsable de service (rôle personnalisé)', 'responsable@demo.gestad.app', 'Aïcha', 'Bamba', $e, $responsable, [$abj], $rh);
        $employes = [
            $this->account('Employé', 'employe@demo.gestad.app', 'Yao', 'Kouassi', $e, $roles['roles']['EMPLOYE'], [$abj], $rh),
            $this->account('Employé', 'employe2@demo.gestad.app', 'Aminata', 'Ouattara', $e, $roles['roles']['EMPLOYE'], [$abj], $it),
        ];
        foreach ([['Kader', 'Sanogo', $daf], ['Mariam', 'Coulibaly', $rh], ['Ibrahim', 'Bakayoko', $it], ['Salimata', 'Yéo', $daf]] as $i => [$pr, $nm, $sv]) {
            $employes[] = $this->account('Employé', sprintf('employe%d@demo.gestad.app', $i + 3), $pr, $nm, $e, $roles['roles']['EMPLOYE'], [$abj], $sv, false, false);
        }
        $employeBke = $this->account('Employé (Bouaké)', 'employe.bouake@demo.gestad.app', 'Koffi', 'N\'Guessan', $e, $roles['roles']['EMPLOYE'], [$bke], $log);
        $this->em->flush();

        $papeterie = $this->categorie($e, 'Papeterie');
        $conso = $this->categorie($e, 'Consommables informatiques');
        $entretien = $this->categorie($e, 'Entretien & hygiène');

        $f1 = $this->fournisseur($e, 'Papeterie Moderne SARL', 'M. Bamba', 'commercial@papeterie-moderne.example', '+225 27 20 00 00 01');
        $f2 = $this->fournisseur($e, 'Info Distribution CI', 'Mme Soro', 'ventes@info-distribution.example', '+225 27 20 00 00 02');
        $f3 = $this->fournisseur($e, 'Hygiène Plus', 'M. Touré', 'contact@hygiene-plus.example', '+225 27 20 00 00 03');
        $this->fournisseur($e, 'Bureau Express', 'Mme Gnahoré', 'bureau.express@example.com', '+225 27 20 00 00 04');

        // Centre Abidjan : catalogue complet
        $defs = [
            // sku, désignation, unité, catégorie, seuil, cible, prix, stock reçu (réception fournisseur)
            ['PAP001', 'Papier A4 80g', 'Rame', $papeterie, 50, 150, 2800, 120],
            ['STY002', 'Stylo bille bleu', 'Boîte de 50', $papeterie, 50, 120, 3500, 35],
            ['CLAS003', 'Classeur dos 8 cm', 'Pièce', $papeterie, 20, 60, 1200, 18],
            ['POST004', 'Post-it 76x76', 'Bloc', $papeterie, 50, 200, 450, 200],
            ['ENV005', 'Enveloppe DL', 'Paquet de 100', $papeterie, 50, 120, 1800, 90],
            ['AGR006', 'Agrafes 24/6', 'Boîte', $papeterie, 15, 40, 600, 60],
            ['CHM009', 'Chemise cartonnée', 'Paquet de 50', $papeterie, 10, 40, 2200, 25],
            ['SUR010', 'Surligneur jaune', 'Boîte de 10', $papeterie, 10, 30, 1500, 8],
            ['CAH011', 'Cahier 200 pages', 'Pièce', $papeterie, 20, 80, 700, 70],
            ['RUB012', 'Ruban adhésif', 'Rouleau', $papeterie, 10, 40, 350, 0],
            ['TON007', 'Cartouche toner HP 85A', 'Cartouche', $conso, 5, 12, 42000, 6],
            ['CLE008', 'Clé USB 32 Go', 'Pièce', $conso, 5, 15, 5500, 3],
            ['TON013', 'Cartouche d\'encre noire', 'Cartouche', $conso, 4, 12, 18500, 9],
            ['PIL014', 'Piles AA', 'Pack de 4', $conso, 10, 30, 1200, 22],
            ['SOU015', 'Souris sans fil', 'Pièce', $conso, 3, 8, 7500, 5],
            ['SAV016', 'Savon liquide', 'Bidon 5 L', $entretien, 4, 10, 6500, 7],
            ['ESS017', 'Essuie-tout', 'Lot de 6', $entretien, 6, 20, 2800, 4],
        ];
        $articles = $this->articles($e, $abj, $defs);
        $this->receptionner($abj, $f1, '-45 days', 'BL-2026-118', $articles, ['PAP001', 'STY002', 'CLAS003', 'POST004', 'ENV005', 'AGR006', 'CHM009', 'SUR010', 'CAH011'], $defs);
        $bon2 = $this->receptionner($abj, $f2, '-30 days', 'BL-7742', $articles, ['TON007', 'CLE008', 'TON013', 'PIL014', 'SOU015'], $defs);
        $bon3 = $this->receptionner($abj, $f3, '-12 days', 'BL-HP-0031', $articles, ['SAV016', 'ESS017'], $defs);

        $bon1 = $this->em->getRepository(\App\Achats\Domain\Entity\BonReception::class)->findOneBy(['centre' => $abj, 'numeroBon' => 'BR-' . date('Y') . '-0001']);

        // Factures & règlements (payée / partielle / non payée en retard / non payée à échoir)
        $fa = $this->facture($e, $abj, 'FAC-2026-0457', $f1, $bon1, '-44 days', '-14 days');
        $fb = $this->facture($e, $abj, 'FAC-INFO-9981', $f2, $bon2, '-29 days', '-1 days');
        $fc = $this->facture($e, $abj, 'FAC-HP-2210', $f3, $bon3, '-11 days', '+19 days');
        $this->em->flush();
        $this->paiement->execute($fa, ['montantRegle' => $fa->getResteAPayer(), 'modePaiement' => 'VIREMENT', 'datePaiement' => '-20 days', 'reference' => 'VIR-0092']);
        $this->paiement->execute($fb, ['montantRegle' => '150000', 'modePaiement' => 'CHEQUE', 'datePaiement' => '-10 days', 'reference' => 'CHQ-5541']);

        // Commandes à différents stades
        $this->commande($e, $abj, $f1, Commande::BROUILLON, [['STY002', 90], ['CLAS003', 42]], $articles, '-1 days');
        $this->commande($e, $abj, $f2, Commande::ENVOYEE, [['CLE008', 12], ['TON007', 6]], $articles, '-4 days');
        $this->commande($e, $abj, $f3, Commande::PARTIELLE, [['SAV016', 10], ['ESS017', 16]], $articles, '-9 days', ['SAV016' => 4]);
        $this->commande($e, $abj, $f1, Commande::RECUE, [['PAP001', 60]], $articles, '-25 days', ['PAP001' => 60]);

        // Demandes : parcours variés sur 60 jours
        $this->demandesAbidjan($abj, $gest, $employes, $articles);

        // Centre Bouaké : petit catalogue autonome (données isolées par centre)
        $defsBke = [
            ['PAP001', 'Papier A4 80g', 'Rame', $papeterie, 20, 60, 2800, 40],
            ['STY002', 'Stylo bille bleu', 'Boîte de 50', $papeterie, 10, 30, 3500, 6],
            ['CLAS003', 'Classeur dos 8 cm', 'Pièce', $papeterie, 8, 25, 1200, 30],
            ['TON007', 'Cartouche toner HP 85A', 'Cartouche', $conso, 2, 6, 42000, 3],
            ['PIL014', 'Piles AA', 'Pack de 4', $conso, 5, 15, 1200, 4],
        ];
        $articlesBke = $this->articles($e, $bke, $defsBke);
        $this->receptionner($bke, $f1, '-18 days', 'BL-BKE-004', $articlesBke, array_column($defsBke, 0), $defsBke);
        $d = $this->workflow->soumettre($employeBke, $bke, ['motif' => 'Rentrée de l\'agence', 'lignes' => [['article' => $articlesBke['PAP001']->getId(), 'quantiteDemandee' => 10], ['article' => $articlesBke['CLAS003']->getId(), 'quantiteDemandee' => 5]]]);
        $this->workflow->valider($d, $gestBke, []);
        $d2 = $this->workflow->soumettre($employeBke, $bke, ['motif' => 'Imprimante de l\'accueil', 'lignes' => [['article' => $articlesBke['TON007']->getId(), 'quantiteDemandee' => 1]]]);
    }

    private function demandesAbidjan(Centre $centre, Utilisateur $gest, array $employes, array $articles): void
    {
        $plans = [
            // [employé idx, motif, lignes [sku, qté], jours passés, issue]
            [0, 'Fournitures du trimestre', [['PAP001', 10], ['STY002', 5]], 55, 'livree'],
            [1, 'Renouvellement imprimante', [['TON007', 2], ['CLE008', 1]], 48, 'livree'],
            [2, 'Archivage dossiers RH', [['CLAS003', 6], ['CHM009', 4]], 40, 'livree'],
            [3, 'Sessions de formation', [['PAP001', 12], ['POST004', 20], ['SUR010', 2]], 33, 'livree'],
            [4, 'Fournitures mensuelles', [['PAP001', 8], ['STY002', 6], ['AGR006', 3]], 26, 'livree'],
            [0, 'Réunion de direction', [['POST004', 15], ['STY002', 4]], 19, 'livree'],
            [5, 'Matériel de nettoyage', [['SAV016', 2], ['ESS017', 3]], 14, 'livree'],
            [1, 'Remplacement souris', [['SOU015', 2], ['PIL014', 4]], 9, 'livree_partielle'],
            [2, 'Rentrée', [['CAH011', 30], ['STY002', 20]], 7, 'refusee'],
            [3, 'Fournitures du bureau', [['ENV005', 10], ['PAP001', 6]], 5, 'en_livraison'],
            [4, 'Impression des rapports', [['PAP001', 20], ['TON013', 2]], 3, 'validee'],
            [0, 'Fournitures urgentes', [['STY002', 3], ['CLAS003', 2]], 1, 'en_attente'],
            [1, 'Archivage', [['CHM009', 6], ['RUB012', 5]], 1, 'en_attente'],
            [2, 'Matériel de bureau', [['POST004', 10], ['CAH011', 5]], 0, 'en_attente'],
        ];

        foreach ($plans as [$emp, $motif, $rows, $jours, $issue]) {
            $employe = $employes[$emp];
            $d = $this->workflow->soumettre($employe, $centre, ['motif' => $motif, 'lignes' => array_map(fn ($r) => ['article' => $articles[$r[0]]->getId(), 'quantiteDemandee' => $r[1]], $rows)]);
            $d->setDateDemande(new \DateTimeImmutable(sprintf('-%d days 09:%02d', $jours, 10 + $d->getId() % 40)));

            switch ($issue) {
                case 'refusee':
                    $this->workflow->refuser($d, $gest, 'Budget du trimestre épuisé : merci de renouveler la demande le mois prochain.');
                    break;
                case 'validee':
                    $this->workflow->valider($d, $gest, []);
                    break;
                case 'en_livraison':
                    $this->workflow->valider($d, $gest, []);
                    $this->workflow->livrer($d, $gest);
                    break;
                case 'livree':
                case 'livree_partielle':
                    $accord = [];
                    if ($issue === 'livree_partielle') {
                        $first = $d->getLignes()->first();
                        $accord = ['lignes' => [['id' => $first->getId(), 'quantiteAccordee' => max(1, $first->getQuantiteDemandee() - 1)]]];
                    }
                    $this->workflow->valider($d, $gest, $accord);
                    $this->workflow->livrer($d, $gest);
                    $this->workflow->accuserReception($d, $employe, 'Reçu conforme');
                    break;
            }
        }
    }

    // ------------------------------------------------------------------ Entreprise 2 (isolation des données)

    private function seedIvoireServices(): void
    {
        $e = $this->entreprise('Ivoire Services SA', 'SIV', 'contact@ivoire-services.example', 'STARTER');
        $roles = $this->provisioner->provisionRoles($e);
        $centre = $this->centre($e, 'Siège Yamoussoukro', 'YAM');
        $adm = $this->service($e, 'Administration', 'ADM');

        $this->account('Administrateur (2e entreprise)', 'admin@ivoire.gestad.app', 'Jean', 'Aka', $e, $roles['admin'], [], $adm);
        $gest = $this->account('Gestionnaire (2e entreprise)', 'stock@ivoire.gestad.app', 'Nadège', 'Koffi', $e, $roles['roles']['GESTIONNAIRE'], [$centre], $adm);
        $emp = $this->account('Employé (2e entreprise)', 'employe@ivoire.gestad.app', 'Paul', 'Zadi', $e, $roles['roles']['EMPLOYE'], [$centre], $adm);
        $this->em->flush();

        $cat = $this->categorie($e, 'Bureautique');
        $f = $this->fournisseur($e, 'Librairie du Centre', 'M. Diomandé', 'librairie@example.com', '+225 27 30 00 00 10');
        $defs = [
            ['PAP-A4', 'Papier A4 70g', 'Rame', $cat, 20, 60, 2500, 80],
            ['STY-BL', 'Stylo bleu', 'Boîte de 50', $cat, 5, 15, 3200, 4],
            ['CHEM', 'Chemise à rabats', 'Paquet de 25', $cat, 5, 20, 1800, 12],
        ];
        $articles = $this->articles($e, $centre, $defs);
        $this->receptionner($centre, $f, '-15 days', 'BL-LC-77', $articles, array_column($defs, 0), $defs);
        $d = $this->workflow->soumettre($emp, $centre, ['motif' => 'Fournitures', 'lignes' => [['article' => $articles['PAP-A4']->getId(), 'quantiteDemandee' => 5]]]);
    }

    // ------------------------------------------------------------------ Helpers

    private function entreprise(string $nom, string $code, string $email, string $plan): Entreprise
    {
        $e = (new Entreprise())->setNom($nom)->setCode($code)->setEmail($email)->setDevise('FCFA');
        $this->provisioner->appliquerPlan($e, $plan);
        if ($plan !== 'ESSAI') {
            $e->setDateExpiration(new \DateTimeImmutable('+1 year'));
        }
        $this->em->persist($e);

        return $e;
    }

    private function centre(Entreprise $e, string $nom, string $code): Centre
    {
        $c = (new Centre())->setEntreprise($e)->setNom($nom)->setCode($code);
        $this->em->persist($c);

        return $c;
    }

    private function service(Entreprise $e, string $nom, string $code): Service
    {
        $s = (new Service())->setEntreprise($e)->setNom($nom)->setCode($code);
        $this->em->persist($s);

        return $s;
    }

    private function categorie(Entreprise $e, string $nom): CategorieArticle
    {
        $c = (new CategorieArticle())->setEntreprise($e)->setNom($nom);
        $this->em->persist($c);

        return $c;
    }

    private function fournisseur(Entreprise $e, string $nom, string $contact, string $email, string $tel): Fournisseur
    {
        $f = (new Fournisseur())->setEntreprise($e)->setRaisonSociale($nom)->setContactNom($contact)->setEmail($email)->setTelephone($tel);
        $this->em->persist($f);

        return $f;
    }

    /** @param list<array<int,mixed>> $accounts */
    private function account(string $profil, string $email, string $prenom, string $nom, ?Entreprise $e, ?Role $role, array $centres, ?Service $service, bool $super = false, bool $listed = true): Utilisateur
    {
        $u = (new Utilisateur())->setEmail($email)->setPrenom($prenom)->setNom($nom)->setEntreprise($e)->setRole($role)->setCentres($centres)->setService($service);
        if ($super) {
            $u->setRoles([Utilisateur::ROLE_SUPER_ADMIN]);
        }
        $u->setPassword($this->hasher->hashPassword($u, $this->password));
        $this->em->persist($u);
        if ($listed) {
            $this->accounts[] = [$profil, $email];
        }

        return $u;
    }

    /** @return array<string, Article> */
    private function articles(Entreprise $e, Centre $centre, array $defs): array
    {
        $out = [];
        foreach ($defs as [$sku, $des, $unite, $cat, $seuil, $cible, $prix]) {
            $a = (new Article())->setEntreprise($e)->setCentre($centre)->setCodeSku($sku)->setDesignation($des)->setUnite($unite)
                ->setCategorie($cat)->setSeuilAlerteMin($seuil)->setSeuilReapproCible($cible)->setPrixMoyenHt(number_format((float) $prix, 2, '.', ''));
            $this->em->persist($a);
            $out[$sku] = $a;
        }
        $this->em->flush();

        return $out;
    }

    private function receptionner(Centre $centre, Fournisseur $fournisseur, string $date, string $bl, array $articles, array $skus, array $defs)
    {
        $byKey = [];
        foreach ($defs as $d) {
            $byKey[$d[0]] = $d;
        }
        $lignes = [];
        foreach ($skus as $sku) {
            if (($byKey[$sku][7] ?? 0) > 0) {
                $lignes[] = ['article' => $articles[$sku]->getId(), 'quantiteRecue' => $byKey[$sku][7], 'prixUnitaireHt' => $byKey[$sku][6]];
            }
        }

        return $this->reception->execute($centre, ['fournisseur' => $fournisseur->getId(), 'numeroBonLivraison' => $bl, 'dateEntree' => $date, 'lignes' => $lignes]);
    }

    private function facture(Entreprise $e, Centre $centre, string $numero, Fournisseur $f, $bon, string $date, string $echeance): FactureFournisseur
    {
        $total = '0';
        foreach ($bon->getLignes() as $l) {
            $total = bcadd($total, bcmul((string) $l->getQuantiteRecue(), $l->getPrixUnitaireHt() ?? '0', 2), 2);
        }
        $facture = (new FactureFournisseur())->setEntreprise($e)->setCentre($centre)->setNumeroFacture($numero)->setFournisseur($f)->setBonReception($bon)
            ->setMontantTotal($total)->setDateFacture(new \DateTimeImmutable($date))->setDateEcheance(new \DateTimeImmutable($echeance));
        $this->em->persist($facture);

        return $facture;
    }

    private function commande(Entreprise $e, Centre $centre, Fournisseur $f, string $statut, array $rows, array $articles, string $date, array $recues = []): void
    {
        $c = (new Commande())->setEntreprise($e)->setCentre($centre)->setFournisseur($f)
            ->setNumero($this->numeros->next('CMD', Commande::class, 'numero', $e->getId()))
            ->setDateCommande(new \DateTimeImmutable($date))->setStatut($statut);
        if ($statut !== Commande::BROUILLON) {
            $c->setDateEnvoi(new \DateTimeImmutable($date));
        }
        foreach ($rows as [$sku, $qte]) {
            $c->addLigne((new LigneCommande())->setArticle($articles[$sku])->setQuantite($qte)->setQuantiteRecue($recues[$sku] ?? 0)
                ->setPrixUnitaireHt($articles[$sku]->getPrixMoyenHt()));
        }
        $this->em->persist($c);
        $this->em->flush();
    }
}

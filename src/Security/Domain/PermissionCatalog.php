<?php

namespace App\Security\Domain;

/**
 * Catalogue des modules (entrées de menu) et des actions spéciales du produit.
 * Les droits sont ensuite attribués dynamiquement, entreprise par entreprise, via les rôles
 * (matrice module × CRUD + actions). Le catalogue lui-même est versionné avec le code.
 *
 * Lettres CRUD : R = lecture, C = création, U = modification, D = suppression.
 */
final class PermissionCatalog
{
    public const LETTERS = ['R', 'C', 'U', 'D'];

    /** @return list<array{code:string,titre:string,icon:string,modules:list<array<string,mixed>>}> */
    public static function groupes(): array
    {
        return [
            ['code' => 'dashboard', 'titre' => 'Tableau de bord', 'icon' => 'LayoutDashboard', 'modules' => [
                ['code' => 'dashboard', 'titre' => 'Tableau de bord', 'lien' => '/', 'icon' => 'LayoutDashboard', 'letters' => ['R'],
                    'actions' => [['code' => 'dashboard.stats', 'libelle' => 'Voir les indicateurs globaux du centre']]],
            ]],
            ['code' => 'demandes', 'titre' => 'Demandes & sorties', 'icon' => 'ClipboardList', 'modules' => [
                ['code' => 'demandes', 'titre' => 'Demandes & sorties', 'lien' => '/demandes', 'icon' => 'ClipboardList', 'letters' => ['R', 'C', 'D'],
                    'actions' => [
                        ['code' => 'demandes.voir_toutes', 'libelle' => 'Voir les demandes de tout le centre'],
                        ['code' => 'demandes.valider', 'libelle' => 'Valider / ajuster / refuser les demandes'],
                        ['code' => 'demandes.livrer', 'libelle' => 'Émettre les bons de sortie (livraison)'],
                    ]],
            ]],
            ['code' => 'stock', 'titre' => 'Stock', 'icon' => 'Boxes', 'modules' => [
                ['code' => 'articles', 'titre' => 'Articles', 'lien' => '/articles', 'icon' => 'Boxes', 'letters' => ['R', 'C', 'U', 'D'],
                    'actions' => [['code' => 'stock.ajuster', 'libelle' => 'Ajuster le stock (inventaire)']]],
                ['code' => 'alertes', 'titre' => 'Alertes & réapprovisionnement', 'lien' => '/alertes', 'icon' => 'Bell', 'letters' => ['R'], 'actions' => []],
                ['code' => 'mouvements', 'titre' => 'Mouvements de stock', 'lien' => '/mouvements', 'icon' => 'ArrowLeftRight', 'letters' => ['R'], 'actions' => []],
            ]],
            ['code' => 'achats', 'titre' => 'Achats', 'icon' => 'ShoppingCart', 'modules' => [
                ['code' => 'fournisseurs', 'titre' => 'Fournisseurs', 'lien' => '/fournisseurs', 'icon' => 'Truck', 'letters' => ['R', 'C', 'U', 'D'], 'actions' => []],
                ['code' => 'commandes', 'titre' => 'Commandes fournisseurs', 'lien' => '/commandes', 'icon' => 'ShoppingCart', 'letters' => ['R', 'C', 'U', 'D'],
                    'actions' => [['code' => 'commandes.envoyer', 'libelle' => 'Envoyer les commandes aux fournisseurs']]],
                ['code' => 'receptions', 'titre' => 'Réceptions & entrées de stock', 'lien' => '/receptions', 'icon' => 'PackageCheck', 'letters' => ['R', 'C'], 'actions' => []],
            ]],
            ['code' => 'finance', 'titre' => 'Finance', 'icon' => 'Wallet', 'modules' => [
                ['code' => 'factures', 'titre' => 'Factures & budget', 'lien' => '/factures', 'icon' => 'Wallet', 'letters' => ['R', 'C', 'U', 'D'],
                    'actions' => [['code' => 'factures.payer', 'libelle' => 'Enregistrer les règlements']]],
            ]],
            ['code' => 'rapports', 'titre' => 'Rapports', 'icon' => 'BarChart3', 'modules' => [
                ['code' => 'rapports', 'titre' => 'Analyse de consommation', 'lien' => '/rapports', 'icon' => 'BarChart3', 'letters' => ['R'], 'actions' => []],
            ]],
            ['code' => 'administration', 'titre' => 'Administration', 'icon' => 'Settings', 'modules' => [
                ['code' => 'entreprise', 'titre' => 'Mon entreprise', 'lien' => '/parametres/entreprise', 'icon' => 'Building2', 'letters' => ['R', 'U'], 'actions' => []],
                ['code' => 'centres', 'titre' => 'Centres de gestion', 'lien' => '/parametres/centres', 'icon' => 'MapPin', 'letters' => ['R', 'C', 'U', 'D'], 'actions' => []],
                ['code' => 'services', 'titre' => 'Services', 'lien' => '/parametres/services', 'icon' => 'Network', 'letters' => ['R', 'C', 'U', 'D'], 'actions' => []],
                ['code' => 'utilisateurs', 'titre' => 'Utilisateurs', 'lien' => '/parametres/utilisateurs', 'icon' => 'Users', 'letters' => ['R', 'C', 'U', 'D'], 'actions' => []],
                ['code' => 'roles', 'titre' => 'Rôles & permissions', 'lien' => '/parametres/roles', 'icon' => 'ShieldCheck', 'letters' => ['R', 'C', 'U', 'D'], 'actions' => []],
                ['code' => 'journal', 'titre' => 'Journal d\'activité', 'lien' => '/parametres/journal', 'icon' => 'History', 'letters' => ['R'], 'actions' => []],
            ]],
        ];
    }

    /** @return array<string, array<string,mixed>> modules indexés par code */
    public static function modules(): array
    {
        $out = [];
        foreach (self::groupes() as $g) {
            foreach ($g['modules'] as $m) {
                $out[$m['code']] = $m;
            }
        }
        return $out;
    }

    /** @return list<string> */
    public static function actions(): array
    {
        $out = [];
        foreach (self::modules() as $m) {
            foreach ($m['actions'] as $a) {
                $out[] = $a['code'];
            }
        }
        return $out;
    }

    /** Nettoie une matrice de permissions reçue du client : modules connus, lettres valides, R implicite. */
    public static function sanitizePermissions(array $permissions): array
    {
        $modules = self::modules();
        $clean = [];
        foreach ($permissions as $module => $letters) {
            if (!isset($modules[$module])) {
                continue;
            }
            $set = array_values(array_intersect(self::LETTERS, str_split(strtoupper((string) (is_array($letters) ? implode('', $letters) : $letters)))));
            $set = array_values(array_intersect($set, $modules[$module]['letters']));
            if ($set) {
                $clean[$module] = implode('', array_unique(array_merge(['R'], $set)));
                // ordre canonique C-R-U-D
                $clean[$module] = implode('', array_values(array_intersect(['C', 'R', 'U', 'D'], str_split($clean[$module]))));
            }
        }
        return $clean;
    }

    public static function sanitizeActions(array $actions): array
    {
        return array_values(array_intersect(self::actions(), array_map('strval', $actions)));
    }

    /** Rôles par défaut créés pour chaque nouvelle entreprise (modifiables ensuite). */
    public static function rolesParDefaut(): array
    {
        return [
            ['code' => 'GESTIONNAIRE', 'nom' => 'Gestionnaire de stock', 'description' => 'Gère le stock, valide les demandes et passe les commandes.',
                'permissions' => ['dashboard' => 'R', 'demandes' => 'CRD', 'articles' => 'CRUD', 'alertes' => 'R', 'mouvements' => 'R', 'fournisseurs' => 'CRUD', 'commandes' => 'CRUD', 'receptions' => 'CR', 'rapports' => 'R'],
                'actions' => ['dashboard.stats', 'demandes.voir_toutes', 'demandes.valider', 'demandes.livrer', 'stock.ajuster', 'commandes.envoyer']],
            ['code' => 'COMPTABLE', 'nom' => 'Comptable', 'description' => 'Suit les factures fournisseurs, les règlements et le budget.',
                'permissions' => ['dashboard' => 'R', 'demandes' => 'CR', 'fournisseurs' => 'R', 'receptions' => 'R', 'factures' => 'CRUD', 'rapports' => 'R'],
                'actions' => ['dashboard.stats', 'factures.payer']],
            ['code' => 'EMPLOYE', 'nom' => 'Employé', 'description' => 'Fait des demandes de fournitures et confirme leur réception.',
                'permissions' => ['dashboard' => 'R', 'demandes' => 'CRD'],
                'actions' => []],
        ];
    }
}

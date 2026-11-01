# GESTAD — Backend (Symfony 7.4 / PostgreSQL)

API de gestion des stocks administratifs (fournitures & consommables), multi-entreprises (SaaS) et multi-centres.
Structure calquée sur le backend de référence : `src/<Module>/{Domain,Application,Infrastructure,UI}`.

## Modules
- `Security` : utilisateurs, JWT, rôles (SUPER_ADMIN > ADMIN > GESTIONNAIRE / COMPTABLE > EMPLOYE)
- `Organisation` : entreprises (tenants), centres, services
- `Stock` : articles, catégories, mouvements (traçabilité), ajustements d'inventaire
- `Achats` : fournisseurs, commandes, bons de réception (entrée de stock), factures, règlements
- `Demandes` : demandes de matériel → validation → bon de sortie → accusé de réception (sortie de stock)
- `Reporting` : tableau de bord, réapprovisionnement (besoin = cible − stock), consommation, échéancier

## SaaS & permissions dynamiques
- Chaque **entreprise** est un espace isolé (centres, services, utilisateurs, rôles, données). Plans et limites (centres / utilisateurs / expiration) gérés par le super administrateur.
- Les **rôles sont propres à chaque entreprise** : matrice module × (Lire/Créer/Modifier/Supprimer) + actions spéciales (valider une demande, payer une facture…), éditable depuis *Paramètres › Rôles & permissions*. Le rôle système « Administrateur » est verrouillé.
- Le catalogue des modules/actions est dans `src/Security/Domain/PermissionCatalog.php` ; les contrôleurs se protègent avec `#[RequirePermission('module', 'lettre')]`.
- Le **journal d'activité** trace toutes les écritures par entreprise et par centre.

## Démarrage
```bash
composer install
# .env.local (non versionné) : DATABASE_URL="postgresql://USER:PASS@127.0.0.1:5432/gestad?serverVersion=16&charset=utf8"
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console app:seed-demo          # 2 entreprises de démo + comptes (mot de passe : Gestad@2026) ; --reset pour tout recréer
php -S 127.0.0.1:8010 -t public       # ou: symfony server:start --port=8010
```
Doc API : `/api/doc`. Chaque requête `/api/*` exige `X-APP-SECURITY-CODE` (= `APP_SECURITY_CODE`) ;
le centre actif se choisit via `X-Centre-Id`.

## Production
Définir en variables d'environnement : `APP_SECRET`, `DATABASE_URL`, `JWT_SECRET` (≥ 32 car.), `APP_SECURITY_CODE`,
`CORS_ALLOW_ORIGIN` (regex de l'origine du front). Ne pas lancer `app:seed-demo` avec le mot de passe par défaut.

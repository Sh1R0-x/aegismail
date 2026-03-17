# E2E / Smoke / Readiness

## Scope

Cette procédure prépare une validation locale de type staging pour AEGIS MAILING V1 sans Docker, sans Gmail, sans multi-provider et sans nouvelle feature produit.

Principes gardés :

- une seule boîte `ovh_mx_plan`
- réception IMAP uniquement
- envoi SMTP uniquement
- une seule queue sortante `mail-outbound`
- statuts gelés inchangés

## Outillage réellement en place

Le repo contient désormais un minimum E2E local reproductible :

- `@playwright/test` dans `app/package.json`
- config Playwright : `app/playwright.config.ts`
- script de boot local E2E : `scripts/e2e-serve.ps1`
- seed dédiée : `app/database/seeders/SmokeTestSeeder.php`
- suite smoke : `app/tests/e2e/smoke.spec.ts`

La suite smoke utilise :

- Chromium
- build Vite statique
- serveur Laravel dédié `php artisan serve` sur `http://127.0.0.1:8811` par défaut
- base SQLite dédiée `app/database/e2e.sqlite`
- gateway `stub`

## Commandes locales

Depuis `C:\Dev\Aegis mail\app` :

```powershell
npm install
npm run e2e:install
npm run test:e2e:smoke
```

Mode headed :

```powershell
npm run test:e2e:smoke:headed
```

## Ce que fait le boot E2E

`scripts/e2e-serve.ps1` :

1. garantit la présence de `.env`
2. garantit un `APP_KEY`
3. crée `database/e2e.sqlite`
4. force les variables locales utiles :
   - `DB_CONNECTION=sqlite`
   - `DB_DATABASE=.../database/e2e.sqlite`
   - `QUEUE_CONNECTION=database`
   - `CACHE_STORE=database`
   - `SESSION_DRIVER=database`
   - `MAIL_GATEWAY_DRIVER=stub`
   - `APP_URL=http://127.0.0.1:8811` par défaut
5. exécute `php artisan migrate:fresh --seed --seeder=SmokeTestSeeder`
6. exécute `npm run build`
7. démarre `php artisan serve --host=127.0.0.1 --port=8811 --no-reload`

Important :

- la smoke démarre toujours son serveur Laravel dédié pour garder une base seedée propre et déterministe
- le port peut être surchargé via `AEGIS_E2E_PORT` si `8811` est déjà pris
- la suite smoke reconstruit sa base dédiée à chaque exécution
- aucun worker queue permanent n’est requis pour ce scénario smoke

## Données seedées pour le smoke

`SmokeTestSeeder` prépare un socle métier cohérent :

- mailbox OVH unique configurée et saine
- `settings.general`, `settings.mail`, `settings.deliverability`
- organisations et contacts
- un template actif
- un draft en `draft`
- un draft en `scheduled`
- une campagne `scheduled`
- une campagne `sent`
- des recipients `queued`, `replied`, `auto_replied`, `hard_bounced`
- des threads/messages entrants et sortants pour dashboard, activity et timeline

## Parcours smoke couverts

### Navigation

- ouverture `/dashboard`
- navigation Sidebar :
  - `/contacts`
  - `/organizations`
  - `/mails`
  - `/drafts`
  - `/templates`
  - `/campaigns`
  - `/activity`
  - `/settings`
  - `/users`
- ouverture d’actions réelles à fort impact :
  - `Contacts -> Fiche`
  - `Organisations -> Fiche`
  - `Mails -> Voir`
  - `Campagnes -> Détails`

### Parcours métier minimal

- création d’un template depuis l’UI
- ouverture du composer draft
- sauvegarde d’un brouillon
- exécution du preflight
- planification d’un draft
- vérification du retour en liste avec statut `scheduled`

### Robustesse frontend basique

- absence d’erreurs `pageerror`
- absence d’erreurs `console.error`
- message d’erreur SMTP visible et précis quand l’adresse d’envoi est invalide

## Readiness checklist

### Prérequis environnement

- PHP CLI opérationnel
- Composer installé
- Node.js + npm installés
- extensions SQLite actives
- `vendor/` et `node_modules/` présents

### Base / migrations

- `php artisan migrate --ansi` doit passer
- pour le smoke : `migrate:fresh --seed --seeder=SmokeTestSeeder`
- vérifier la présence des tables `jobs`, `cache`, `sessions`, `mail_*`, `contacts`, `organizations`

### Storage / cache / queues

- local : `database` acceptable pour queue/cache/session
- staging/release : Redis recommandé pour queue/cache
- une seule queue sortante à surveiller : `mail-outbound`
- queue sync distincte côté infra seulement : `mail-sync`

### SMTP / IMAP

Avant release réelle, vérifier manuellement :

- `POST /api/settings/mail/test-smtp`
- `POST /api/settings/mail/test-imap`
- credentials OVH valides
- fenêtre d’envoi cohérente
- `send_enabled` et `sync_enabled`

### PostgreSQL / Redis

Si la cible staging/prod n’utilise plus SQLite :

- exécuter les migrations sur PostgreSQL
- vérifier la queue sur Redis ou, à défaut, `database`
- vérifier les locks nécessaires au sync mailbox

### Logs à surveiller

- `storage/logs/laravel.log`
- `mail_events`
- jobs en base : table `jobs`
- `failed_jobs`

Événements importants à surveiller :

- `settings.mail.updated`
- `mail_message.queued`
- `mail_message.dispatch_skipped`
- `mail_message.sent`
- `mail_message.failed`
- `mailbox.sync_skipped_locked`
- `mail_campaign.auto_stopped`

## Cas manuels avant release

1. Configurer une vraie mailbox OVH MX Plan.
2. Lancer les tests IMAP/SMTP depuis Réglages.
3. Créer un draft simple puis multiple.
4. Vérifier le preflight avec cas valides, opt-out, invalides et hard bounce.
5. Planifier un envoi futur et vérifier la création des recipients.
6. Déprogrammer un draft et vérifier qu’un job différé ne part pas ensuite.
7. Forcer un envoi immédiat en environnement contrôlé avec worker actif.
8. Lancer `php artisan mailbox:poll` avec le gateway IMAP réel.
9. Vérifier threading, `replied` vs `auto_replied`, `hard_bounced`, timeline et dashboard.

## Known issues / risques restants

- la sauvegarde mail standard n’écrase plus la signature globale existante quand `global_signature_html/text` arrivent à `null`; un effacement volontaire complet doit maintenant envoyer `clear_signature = true`
- la suite smoke ne couvre pas encore un envoi SMTP réel ni une sync IMAP réelle ; elle valide le flux UI + backend local avec gateway `stub`
- la suite smoke couvre désormais l’ouverture des pages détail Contact, Organisation, Campagne et Thread, mais ne couvre pas encore toutes les mutations associées en UI
- `BACKEND_SCOPE.md` reste plus conservateur que l’état réel du repo sur certaines briques déjà présentes

## Interprétation du feu vert smoke

Un smoke vert signifie :

- l’application démarre localement
- les pages majeures se chargent
- les payloads Inertia critiques sont cohérents
- template + draft + preflight + schedule fonctionnent dans un scénario V1 simplifié

Un smoke vert ne remplace pas :

- un vrai test SMTP/IMAP OVH
- une validation worker/queue longue durée
- une validation staging avec PostgreSQL/Redis si c’est la cible

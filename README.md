# AEGIS MAILING

AEGIS MAILING est un outil de prospection et de suivi d'échanges email orienté délivrabilité, threading robuste et pilotage métier.

Version V1 actuellement figée :

- une seule boîte mail OVH MX Plan
- réception en IMAP uniquement
- envoi en SMTP uniquement
- aucune logique Gmail, Google API, OAuth Google ou multi-provider
- backend métier en Laravel
- moteur mail dédié en Node.js + TypeScript
- SQLite en local, PostgreSQL en cible production
- database queue en local, Redis recommandé en production
- une seule queue d'envoi pour tous les mails
- **pas de Docker, pas de Sail** — développement local natif

## Prérequis

- PHP >= 8.2 avec extensions : pdo_sqlite, mbstring, openssl, tokenizer, xml, ctype, json, bcmath
- Composer
- Node.js >= 18 + npm
- (optionnel) PostgreSQL si on veut tester avec la cible production
- (optionnel) Redis si on veut tester la queue/cache production

Aucun outil conteneurisé (Docker, Sail, docker-compose) n'est utilisé ni prévu.

## Installation locale (première fois)

```powershell
# 1. Installer les dépendances PHP
cd "C:\Dev\Aegis mail\app"
composer install

# 2. Copier l'environnement et générer la clé
copy .env.example .env
php artisan key:generate

# 3. Créer la base SQLite et migrer
New-Item -ItemType File -Path database\database.sqlite -Force
php artisan migrate

# 4. Installer les dépendances JS
npm install

# 5. (optionnel) Installer les dépendances du mail-gateway
cd "C:\Dev\Aegis mail\mail-gateway"
npm install
```

## Lancement du serveur de développement

La procédure locale confirmée et testée est un script PowerShell unique :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1
```

Ce script :

- vérifie `php`, `composer` et `npm`
- vérifie les dépendances `vendor/` et `node_modules/`
- crée `.env` depuis `.env.example` si nécessaire
- génère `APP_KEY` si nécessaire
- crée `database/database.sqlite` si nécessaire
- exécute `php artisan migrate --no-interaction`
- démarre `php artisan serve --host=127.0.0.1 --port=8001 --no-reload`
- démarre `npm run dev -- --host 127.0.0.1 --port 5173`

L'application est accessible sur `http://127.0.0.1:8001/dashboard`.

Pour arrêter proprement :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action stop
```

Pour vérifier l'état :

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1 -Action status
```

## Commandes après reboot / fermeture VS Code

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\dev.ps1
```

C'est tout. Si la base a été supprimée ou corrompue, le script recrée le fichier SQLite mais il faut laisser tourner la migration automatique intégrée.

## Architecture

### Laravel

Laravel porte toute la logique métier :

- réglages généraux, cadence et délivrabilité
- configuration de la mailbox OVH unique
- contacts, organisations et scoring simple
- drafts, templates, campaigns, preflight
- scheduling progressif
- persistance des threads, messages, pièces jointes et événements métier
- projection des payloads Inertia et API

### mail-gateway

Le dossier `mail-gateway/` contient le squelette du moteur mail dédié Node.js + TypeScript.

Responsabilités visées :

- tests IMAP/SMTP
- envoi SMTP réel
- lecture IMAP réelle
- remontée de payloads normalisés vers Laravel

En l'état du repo, Laravel sait déjà parler à un client `stub|http` et les contrats sont documentés.

## Flux métier couverts

### Sortant

Flux V1 :

`draft -> preflight -> campaign -> recipients -> queue mail-outbound -> mail-gateway -> sent|failed`

Points clés :

- mails simples et multiples dans le même produit
- même queue d'envoi pour tout le sortant
- cadence progressive avec fenêtre d'envoi, plafond journalier, plafond horaire, délai minimal, jitter et slow mode
- persistance de `aegis_tracking_id`, `Message-ID`, `In-Reply-To`, `References`
- événements métier dans `mail_events`

### Entrant

Flux V1 :

`mailbox:poll -> SyncMailboxFolderJob -> mail-gateway IMAP -> ingestion Laravel -> threading -> classification -> timeline`

Points clés :

- sync IMAP sur `INBOX` et `SENT`
- polling planifié toutes les 5 minutes
- reprise sur UID avec `last_inbox_uid` et `last_sent_uid`
- lock mailbox+folder pour éviter les sync concurrentes
- idempotence stricte sur UID et `Message-ID`
- création ou mise à jour de `mail_threads`, `mail_messages`, `mail_attachments`, `mail_events`

## Threading et classification

Ordre de rattachement de thread :

1. `In-Reply-To`
2. `References`
3. corrélation `Message-ID` connue
4. heuristique prudente sur sujet normalisé + participants + fenêtre de 30 jours
5. nouveau thread

Classifications entrantes utilisées :

- `human_reply`
- `auto_reply`
- `out_of_office`
- `auto_ack`
- `soft_bounce`
- `hard_bounce`
- `system`
- `unknown`

Règles métier :

- une auto-réponse ne vaut jamais une réponse humaine
- `auto_reply_received` reste distinct de `reply_received`
- `hard_bounce` reste distinct de `soft_bounce`
- un hard bounce met à jour l'exclusion exploitable via `contact_emails.bounce_status`
- une réponse humaine ou un hard bounce peut annuler des relances futures encore en file sur la même campagne/adresse

## Dossiers importants

- `app/` : application Laravel
- `mail-gateway/` : moteur mail dédié TypeScript
- `docs/ai/` : documentation figée de coordination et de contrats

## Documentation à lire en priorité

- `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md`
- `docs/ai/BACKEND_SCOPE.md`
- `docs/ai/AEGIS_MAILING_CODEX_BACKEND.md`
- `docs/ai/BACKEND_CONTRACTS.md`
- `docs/ai/FRONTEND_CONTRACTS.md`
- `docs/ai/DECISIONS_LOG.md`

## Commandes utiles

### Laravel

```powershell
cd "C:\Dev\Aegis mail\app"
composer dump-autoload
php artisan migrate --ansi
php artisan route:list --ansi
php artisan test --ansi
php artisan mailbox:poll
php artisan queue:work --queue=mail-outbound,mail-sync --tries=1 --sleep=1
```

### mail-gateway

```powershell
cd "C:\Dev\Aegis mail\mail-gateway"
npm install
npm run build
```

## Mise en production — cible OVH

La cible de déploiement est un hébergement web OVH classique (mutualisé ou VPS basique).

### Compatible sans difficulté

- Laravel + PHP >= 8.2 (disponible sur les offres OVH web / VPS)
- PostgreSQL ou MySQL (disponible sur les offres OVH mutualisées)
- Vite build statique (`npm run build` en local, déployer le dossier `public/build`)

### Points d'attention pour un OVH mutualisé

- **Queue worker** : un mutualisé OVH n'a pas de processus long. Il faut soit utiliser un cron artisan (`schedule:run`) pour traiter les jobs périodiquement, soit passer sur un VPS pour avoir un vrai worker.
- **Redis** : non disponible sur mutualisé OVH. Utiliser `database` pour queue et cache, ou installer Redis sur un VPS.
- **mail-gateway Node.js** : non exécutable sur un mutualisé classique. Nécessite un VPS ou un service externe si le mail-gateway est activé.
- **Cron** : configurer `* * * * * php artisan schedule:run >> /dev/null 2>&1` côté OVH.
- **HTTPS** : Let's Encrypt est disponible gratuitement sur les offres OVH.

### Recommandation réaliste

Pour la V1 complète (avec queue worker + scheduler + gateway HTTP réel), un **VPS OVH basique** est le minimum viable. Un mutualisé peut convenir pour un mode dégradé sans envoi progressif temps réel.

Important :

- Laravel est prêt pour un déploiement sobre sur VPS
- le repo sait déjà parler à un mail-gateway `stub|http`
- le dossier `mail-gateway/` reste toutefois un squelette de contrats TypeScript et n’est pas encore un serveur Node prêt à lancer en production

Runbook détaillé :

- `docs/ai/DEPLOY_OVH_RUNBOOK.md`

## État actuel V1

Le produit sait déjà :

- gérer les réglages mailbox OVH
- tester IMAP/SMTP
- préparer les drafts, templates et campaigns
- planifier des envois progressifs
- persister les envois sortants
- synchroniser des messages entrants via le contrat IMAP
- rattacher les échanges à des threads
- classifier les réponses et bounces
- alimenter le dashboard CRM et la timeline d'activité

Ce repo ne cherche pas à abstraire plusieurs providers sur la V1. Toute extension future doit préserver ce principe tant qu'aucune décision produit contraire n'est actée.

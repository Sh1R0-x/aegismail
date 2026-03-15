# AEGIS MAILING

AEGIS MAILING est un outil de prospection et de suivi d'échanges email orienté délivrabilité, threading robuste et pilotage métier.

Version V1 actuellement figée :

- une seule boîte mail OVH MX Plan
- réception en IMAP uniquement
- envoi en SMTP uniquement
- aucune logique Gmail, Google API, OAuth Google ou multi-provider
- backend métier en Laravel
- moteur mail dédié en Node.js + TypeScript
- PostgreSQL + Redis en cible produit
- une seule queue d'envoi pour tous les mails

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

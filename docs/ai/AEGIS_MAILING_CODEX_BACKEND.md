# AEGIS MAILING — Spécification backend pour Codex

## Rôle de ce document

Ce fichier est une annexe détaillée.
Les sources de vérité quotidiennes restent `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md`, `docs/ai/BACKEND_SCOPE.md`, `docs/ai/BACKEND_CONTRACTS.md` et `docs/ai/AI_WORKFLOW_METHOD.md`.

## 1. Rôle

Tu prends en charge exclusivement le backend et l’architecture serveur.
Tu ne modifies pas l’UI/UX hors éléments strictement nécessaires à l’exposition des données.

## 2. Contexte figé

- Laravel 12 pour le produit métier
- PostgreSQL
- Redis
- une seule boîte OVH MX Plan
- aucune logique Gmail
- aucun provider alternatif en V1
- même queue pour mails simples et mails multiples
- moteur mail dédié en Node.js + TypeScript

## 3. Objectif backend V1

Construire un backend fiable qui permet :
- configuration d’une boîte MX Plan
- envoi progressif d’e-mails
- synchronisation Inbox / Sent
- stockage des messages, threads, événements et pièces jointes
- détection réponses humaines / auto-réponses / bounces
- scoring simple
- réglages modifiables depuis l’application

## 4. Découpage technique recommandé

### Monorepo / dossier projet
- `/app` Laravel métier
- `/mail-gateway` Node/TypeScript moteur mail

### Responsabilités Laravel
- auth
- users / roles minimum
- settings
- contacts / organizations
- templates / drafts / campaigns
- threads / messages / events (source de vérité métier)
- API interne consommée par le front
- orchestration jobs
- audit log

### Responsabilités mail-gateway
- test IMAP / SMTP
- send SMTP
- sync IMAP Inbox / Sent
- parse MIME
- détection headers `Message-ID`, `In-Reply-To`, `References`
- classification technique du message entrant
- tracking opens / clicks
- débit d’envoi / jitter / scheduling execution
- remontée d’événements vers Laravel

## 5. Contraintes impératives

- ne jamais utiliser Gmail, Socialite Google ou API Google
- ne pas introduire de multi-provider en V1
- ne pas faire de différence de queue entre mail simple et mail multiple
- tout paramètre important doit être administrable depuis l’application
- tout événement significatif doit être historisé
- les actions doivent être idempotentes autant que possible

## 6. Schéma de données minimum

### mailbox_accounts
- id
- user_id nullable
- provider = `ovh_mx_plan`
- email
- display_name
- username
- password_encrypted
- imap_host
- imap_port
- imap_secure
- smtp_host
- smtp_port
- smtp_secure
- sync_enabled
- send_enabled
- last_inbox_uid nullable
- last_sent_uid nullable
- last_sync_at nullable
- health_status
- health_message nullable
- created_at
- updated_at

### organizations
- id
- name
- domain nullable
- website nullable
- notes nullable
- created_at
- updated_at

### contacts
- id
- organization_id nullable
- first_name nullable
- last_name nullable
- full_name nullable
- job_title nullable
- phone nullable
- notes nullable
- status nullable
- created_at
- updated_at

### contact_emails
- id
- contact_id
- email unique per contact
- is_primary
- opt_out_at nullable
- opt_out_reason nullable
- bounce_status nullable
- last_seen_at nullable
- created_at
- updated_at

### mail_templates
- id
- name
- slug unique
- subject_template
- html_template
- text_template
- is_active
- created_by nullable
- created_at
- updated_at

### mail_drafts
- id
- mailbox_account_id
- user_id
- mode enum(`single`,`bulk`)
- template_id nullable
- subject
- html_body
- text_body
- signature_snapshot nullable
- payload_json
- status enum(`draft`,`scheduled`,`cancelled`,`ready`)
- scheduled_at nullable
- created_at
- updated_at

### mail_campaigns
- id
- mailbox_account_id
- user_id
- name
- mode enum(`single`,`bulk`)
- draft_id nullable
- status enum(`draft`,`scheduled`,`running`,`paused`,`completed`,`cancelled`,`failed`)
- send_window_json nullable
- throttling_json nullable
- started_at nullable
- completed_at nullable
- created_at
- updated_at

### mail_recipients
- id
- campaign_id
- organization_id nullable
- contact_id nullable
- contact_email_id nullable
- email
- status enum(`draft`,`queued`,`sent`,`opened`,`clicked`,`replied`,`auto_replied`,`soft_bounced`,`hard_bounced`,`unsubscribed`,`failed`,`cancelled`)
- last_event_at nullable
- scheduled_for nullable
- sent_at nullable
- replied_at nullable
- auto_replied_at nullable
- bounced_at nullable
- unsubscribe_at nullable
- score_bucket nullable
- created_at
- updated_at

### mail_threads
- id
- public_uuid
- mailbox_account_id
- organization_id nullable
- contact_id nullable
- subject_canonical
- first_message_at
- last_message_at
- last_direction enum(`in`,`out`)
- reply_received boolean default false
- auto_reply_received boolean default false
- confidence_score nullable
- status nullable
- created_at
- updated_at

### mail_messages
- id
- thread_id
- mailbox_account_id
- recipient_id nullable
- direction enum(`in`,`out`)
- provider_folder nullable
- provider_uid nullable
- message_id_header unique
- in_reply_to_header nullable
- references_header text nullable
- aegis_tracking_id uuid unique
- from_email
- to_emails jsonb
- cc_emails jsonb nullable
- bcc_emails jsonb nullable
- subject
- html_body nullable
- text_body nullable
- headers_json jsonb
- classification enum(`human_reply`,`auto_reply`,`out_of_office`,`auto_ack`,`soft_bounce`,`hard_bounce`,`system`,`unknown`)
- sent_at nullable
- received_at nullable
- opened_first_at nullable
- clicked_first_at nullable
- created_at
- updated_at

### mail_attachments
- id
- message_id
- original_name
- mime_type
- size_bytes
- storage_disk
- storage_path
- content_id nullable
- disposition nullable
- created_at
- updated_at

### mail_events
- id
- mailbox_account_id
- campaign_id nullable
- recipient_id nullable
- thread_id nullable
- message_id nullable
- event_type
- event_payload jsonb
- occurred_at
- created_at

### settings
- id
- key unique
- value_json
- updated_by nullable
- updated_at

## 7. API interne minimale

### Réglages
- `GET /api/settings`
- `PUT /api/settings/general`
- `PUT /api/settings/mail`
- `PUT /api/settings/deliverability`
- `POST /api/settings/mail/test-imap`
- `POST /api/settings/mail/test-smtp`

### Contacts / organisations
- CRUD standard
- recherche plein texte simple
- import CSV plus tard si besoin, pas bloquant V1

### Templates
- CRUD templates
- duplication template
- preview rendu

### Drafts
- CRUD drafts
- schedule / unschedule
- preview final

### Campaigns / mails
- create from draft
- enqueue send
- cancel
- pause / resume si prévu
- list recipients / statuses

### Threads / messages
- list
- show
- filters by status/classification
- reprogram action manually

## 8. Réglages administrables à exposer

### settings.mail
- sender_email
- sender_name
- global_signature_html
- global_signature_text
- imap_host
- imap_port
- imap_secure
- smtp_host
- smtp_port
- smtp_secure
- mailbox_username
- mailbox_password_encrypted
- send_window_start
- send_window_end

### settings.throttling
- daily_limit_default
- hourly_limit_default
- min_delay_seconds
- jitter_min_seconds
- jitter_max_seconds
- slow_mode_enabled
- stop_on_consecutive_failures
- stop_on_hard_bounce_threshold

### settings.deliverability
- tracking_opens_enabled
- tracking_clicks_enabled
- max_links_warning_threshold
- max_remote_images_warning_threshold
- html_size_warning_kb
- attachment_size_warning_mb

### settings.scoring
- open_points
- click_points
- reply_points
- auto_reply_points
- soft_bounce_points
- hard_bounce_points
- unsubscribe_points
- inactivity_decay_days

## 9. Moteur d’envoi

### Règles
- tout envoi passe par Redis
- tous les types de mails partagent la même queue
- cadence configurable
- jitter léger
- fenêtre d’envoi respectée
- plafonds horaires et journaliers respectés
- arrêt automatique si erreurs anormales

### Flux sortant
1. le draft est validé
2. préflight exécuté
3. campagne/envoi planifié
4. recipients créés
5. jobs placés en queue
6. worker Laravel délègue au mail-gateway
7. mail-gateway envoie SMTP
8. événement `sent` remonté
9. message et headers persistés

## 10. Synchronisation IMAP

### Dossiers V1
- INBOX
- SENT

### Logique
- polling planifié
- reprise sur UID
- locks pour éviter double sync
- idempotence stricte sur `message_id_header` + `provider_uid`

### Flux entrant
1. sync récupère les nouveaux messages
2. parse MIME
3. extrait headers
4. classifie techniquement
5. résout ou crée le thread
6. enregistre message / events / attachments
7. met à jour recipient / campaign / score / timeline
8. annule ou adapte les relances futures selon classification

## 11. Classification messages entrants

### Human reply
- réponse normale d’un humain

### Auto reply
- out of office
- accusé automatique
- auto-réponse système
- probable_auto_reply

### Bounce
- soft_bounce
- hard_bounce

### Règle métier
Une auto-réponse ne vaut pas réponse humaine.
Elle doit rester visible et actionnable.

## 12. Préflight backend

Avant tout envoi :
- vérifier mailbox active
- vérifier SMTP/IMAP testés avec succès récent ou test à la demande
- vérifier version texte présente
- vérifier taille estimée
- vérifier liens/images/attachments
- exclure emails désinscrits ou invalides
- produire warnings et erreurs

## 13. Sécurité

- secrets uniquement en config sécurisée
- mot de passe boîte mail chiffré en base
- rotation possible
- logs sans secrets
- contrôles d’accès sur réglages
- audit logs sur changements critiques

## 14. Tests attendus

### Laravel
- feature tests sur settings, drafts, campaigns, threads
- tests de validation API
- tests de transitions d’état
- tests de règles d’exclusion / opt-out

### mail-gateway
- unit tests parsing headers
- unit tests classification auto-reply / bounce
- tests integration IMAP/SMTP mockés
- tests throttle / queue behavior

### E2E
- flux brouillon → planification → envoi → sync → réponse → timeline

## 15. DoD backend

Le backend V1 est valide si :
- config MX Plan enregistrable et testable
- envoi progressif fonctionnel
- sync Inbox/Sent fiable
- thread matching correct sur headers standards
- auto-réponses séparées des vraies réponses
- bounces reconnus et historisés
- scoring simple calculé
- paramètres modifiables en application

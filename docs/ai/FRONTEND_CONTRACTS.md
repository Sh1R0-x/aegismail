# FRONTEND CONTRACTS

## Purpose

This document freezes the Inertia payload shape exposed by Laravel to the current frontend pages.

Date format used by the current backend:

- `YYYY-MM-DD HH:mm`
- nullable dates are returned as `null`

## Dashboard

### Component

- `Dashboard`

### Props

- `stats`: required object
- `recentReplies`: required array
- `recentAlerts`: required array
- `scheduledSends`: required array

### stats

- `sentToday`: required integer
- `dailyLimit`: required integer
- `healthStatus`: required enum `good|degraded|critical`
- `bounceRate`: required integer
- `activeCampaigns`: required integer
- `scheduledCount`: required integer

### recentReplies[]

- `id`: required integer
- `status`: required string, currently always `replied`
- `from`: required string
- `subject`: required string
- `time`: nullable string

### recentAlerts[]

- `id`: required integer
- `status`: required enum `auto_replied|soft_bounced|hard_bounced|failed`
- `email`: required string
- `detail`: required string
- `time`: nullable string

### scheduledSends[]

- `id`: required integer
- `subject`: required string
- `recipientCount`: required integer
- `scheduledAt`: nullable string

## Contacts

### Component

- `Contacts/Index`

### Props

- `contacts`: required array
- `filters`: required object (echo of active query params — used to hydrate filter state on page load)

### filters (Contacts)

- `search`: required string (empty string when not active)
- `status`: required enum `all|active|bounced|unsubscribed`
- `score`: required enum `all|engaged|interested|warm|cold|excluded`

### contacts[]

- `id`: required integer
- `firstName`: required string
- `lastName`: required string
- `title`: nullable string
- `organization`: nullable string
- `email`: required string
- `score`: required integer
- `scoreLevel`: required enum `cold|warm|interested|engaged|excluded`
- `excluded`: required boolean
- `unsubscribed`: required boolean
- `lastActivityAt`: nullable string

## Organizations

### Component

- `Organizations/Index`

### Props

- `organizations`: required array
- `filters`: required object (echo of active query params — used to hydrate filter state on page load)

### filters (Organizations)

- `search`: required string (empty string when not active)

### organizations[]

- `id`: required integer
- `name`: required string
- `domain`: nullable string
- `contactCount`: required integer
- `sentCount`: required integer
- `lastActivityAt`: nullable string

## Drafts

### Component

- `Drafts/Index`

### Props

- `drafts`: required array
- `templates`: required array (same shape as `Templates/Index > templates[]`, used by MailComposer in edit mode)

### drafts[]

- `id`: required integer
- `subject`: required string
- `recipientCount`: required integer
- `type`: required enum `single|multiple`
- `status`: required enum `draft|scheduled`
- `scheduledAt`: nullable string
- `updatedAt`: nullable string

## Templates

### Component

- `Templates/Index`

### Props

- `templates`: required array

### templates[]

- `id`: required integer
- `name`: required string
- `subject`: required string
- `htmlBody`: nullable string (full HTML template — also used by MailComposer auto-apply)
- `textBody`: nullable string (plain-text version)
- `active`: required boolean
- `usageCount`: required integer
- `updatedAt`: nullable string

## Campaigns

### Component

- `Campaigns/Index`

### Props

- `campaigns`: required array

### campaigns[]

- `id`: required integer
- `name`: required string
- `status`: required enum `draft|scheduled|queued|sending|sent|cancelled|failed`
- `progressPercent`: required integer
- `recipientCount`: required integer
- `openCount`: required integer
- `replyCount`: required integer
- `bounceCount`: required integer
- `scheduledAt`: nullable string
- `updatedAt`: nullable string

## Activity

### Component

- `Activity/Index`

### Props

- `events`: required array

### events[]

- `id`: required integer
- `title`: required string
- `description`: nullable string
- `status`: required enum `sent|replied|auto_replied|soft_bounced|hard_bounced|delivered_if_known`
- `direction`: required enum `outbound|inbound`
- `isAutoReply`: required boolean
- `isBounce`: required boolean
- `date`: nullable ISO-8601 string

## Mails

### Component

- `Mails/Index`

### Props

- `recipients`: required array
- `stats`: required object
- `templates`: required array (same shape as `Templates/Index > templates[]`)
- `filters`: required object (echo of active query params)

### filters (Mails)

- `status`: required string (`all` or any frozen status value)

### stats (Mails)

- `sentToday`: required integer
- `dailyLimit`: required integer

### recipients[]

- `id`: required integer
- `email`: required string
- `subject`: required string
- `status`: required string (any frozen status value)
- `type`: required enum `single|multiple`
- `sentAt`: nullable string
- `campaignId`: nullable integer

---

## Component Contracts

### MailComposer

**File:** `resources/js/Components/Composer/MailComposer.vue`

**Props:**

- `mode`: `'single' | 'multiple'` — default `'single'`
- `draft`: `Object | null` — full draft shape from `GET /api/drafts/{id}` (optional, for edit mode)
- `templates`: `Array` — list items with `htmlBody`/`textBody` for auto-apply

**Draft shape** (from `GET /api/drafts/{id}`):

- `id`: integer
- `templateId`: nullable integer
- `type`: `'single' | 'multiple'`
- `subject`: string
- `htmlBody`: nullable string
- `textBody`: nullable string
- `signatureHtml`: nullable string
- `status`: `'draft' | 'scheduled' | 'queued' | 'sending' | 'sent' | 'failed' | 'cancelled'`
- `scheduledAt`: nullable string
- `recipients`: array of `{ email, name? }`

**Emits:**

- `close` — user dismissed the composer
- `saved(draft)` — draft was saved/updated successfully
- `scheduled(draft)` — draft was scheduled successfully

**API type mapping:**

- Frontend mode `'single'` → POST body `type: 'single'`
- Frontend mode `'multiple'` → POST body `type: 'bulk'` (backend stores `'bulk'`, serializes as `'multiple'`)

### PreflightResult

**File:** `resources/js/Components/Preflight/PreflightResult.vue`

**Props:**

- `result`: `Object` — full preflight response from `POST /api/drafts/{draft}/preflight`

**Preflight API response shape:**

- `ok`: boolean
- `mailboxValid`: boolean
- `hasTextVersion`: boolean
- `hasRemoteImages`: boolean
- `estimatedWeightBytes`: integer
- `recipientSummary.total`: integer
- `recipientSummary.deliverable`: integer
- `recipientSummary.excluded`: integer
- `recipientSummary.optOut`: integer
- `recipientSummary.invalid`: integer
- `deliverability.linkCount`: integer
- `deliverability.remoteImageCount`: integer
- `deliverability.attachmentCount`: integer
- `deliverability.attachmentSizeBytes`: integer
- `deliverability.htmlSizeBytes`: integer
- `errors[]`: array of `{ code, message }` — blocking issues
- `warnings[]`: array of `{ code, message }` — non-blocking advisories
- `deliverableRecipients[]`: array of recipient objects
- `excludedRecipients[]`: array of recipient objects with `reason = 'hard_bounced'`
- `optOutRecipients[]`: array of recipient objects with `reason = 'opt_out'`
- `invalidRecipients[]`: array of recipient objects with `reason = 'invalid_email'`

Text-first behavior used by the backend:

- `textBody` can be the only authored body
- `htmlBody` is optional
- preflight blocks scheduling only when both bodies are empty
- if `htmlBody` is empty but `textBody` exists, backend generates a minimal HTML body at dispatch time

### TemplateEditor

**File:** `resources/js/Components/Templates/TemplateEditor.vue`

**Props:**

- `template`: `Object | null` — null = create mode, Object = edit mode. Shape must match `templates[]` from Templates/Index (needs `id`, `name`, `subject`, `htmlBody`, `textBody`).

**Emits:**

- `close` — user dismissed the editor
- `saved(template)` — template was created or updated

**API calls:**

- Create: `POST /api/templates` with `{ name, subject, htmlBody, textBody }`
- Update: `PUT /api/templates/{id}` with same shape
- `name` and `subject` are both required
- backend also requires at least one of `textBody` or `htmlBody`

**Notes:**

- `htmlBody` preview is rendered in a sandboxed iframe (`sandbox="allow-same-origin"`) — no script execution.
- Used exclusively by `Templates/Index`.

---

## Action visibility status (V1)

| Screen    | Action                | Status               | Reason                                                  |
| --------- | --------------------- | -------------------- | ------------------------------------------------------- |
| Mails     | Voir                  | Disabled             | No thread detail page; `recipients[]` has no `threadId` |
| Campaigns | Détails               | Disabled             | No campaign detail page in V1                           |
| Campaigns | Créer un brouillon    | Active (→/drafts)    | Campaigns born from draft scheduling                    |
| Templates | Nouveau modèle        | Active               | Wired to TemplateEditor create mode                     |
| Templates | Éditer                | Active               | Wired to TemplateEditor edit mode                       |
| Templates | Dupliquer             | Active               | Wired to `POST /api/templates/{id}/duplicate`           |
| Templates | Archiver/Activer      | Active               | Wired to `POST /api/templates/{id}/archive\|activate`   |
| Drafts    | Éditer                | Active               | Loads draft via `GET /api/drafts/{id}` → MailComposer   |
| Drafts    | Dupliquer             | Active               | Wired to `POST /api/drafts/{id}/duplicate`              |
| Drafts    | Déprogrammer          | Active (conditional) | Only shown when `status === 'scheduled'`                |
| Composer  | Sauvegarder brouillon | Active               | `POST /api/drafts` or `PUT /api/drafts/{id}`            |
| Composer  | Vérifier (preflight)  | Active               | Requires saved draft first (disabled otherwise)         |
| Composer  | Planifier             | Active (conditional) | Only shown when `preflight.ok === true`                 |
| Composer  | Aperçu HTML           | Active               | Sandboxed iframe render toggle on HTML body field       |
| Composer  | Pièces jointes        | Not implemented      | Attachment upload API not exposed in V1                 |
| Contacts  | Ajouter un contact    | Disabled             | No contact CRUD API exposed in V1                       |
| Contacts  | Fiche                 | Disabled             | No contact detail page in V1                            |
| Contacts  | E-mails               | Disabled             | No per-contact history page in V1                       |
| Orgs      | Ajouter organisation  | Disabled             | No organization CRUD API exposed in V1                  |
| Orgs      | Fiche                 | Disabled             | No organization detail page in V1                       |
| Orgs      | Historique            | Disabled             | No per-org history page in V1                           |
| Settings  | Enregistrer           | Active               | `PUT /api/settings/mail` — full mail settings payload   |
| Settings  | Tester SMTP           | Active               | `POST /api/settings/mail/test-smtp`                     |
| Settings  | Tester IMAP           | Active               | `POST /api/settings/mail/test-imap`                     |
| Settings  | Signer. (signature)   | Active               | Fetches current settings, then `PUT /api/settings/mail` |

---

## Settings

### Component

- `Settings/Index`

### Props

- `settings`: required object

Expected shape:

- `settings.mail`: full mail settings object (snake_case keys)
- `settings.deliverability`: required object
- `settings.cadence`: required object
- `settings.scoring`: required object
- `settings.signature.global_signature_html`: nullable string
- `settings.signature.global_signature_text`: nullable string

### mail settings prop shape (passed to `SettingsMail.vue`)

- `smtp_host`: nullable string
- `smtp_port`: nullable integer
- `smtp_secure`: nullable boolean
- `imap_host`: nullable string
- `imap_port`: nullable integer
- `imap_secure`: nullable boolean
- `mailbox_username`: nullable string
- `sender_email`: nullable string
- `sender_name`: nullable string
- `send_window_start`: nullable string (HH:mm)
- `send_window_end`: nullable string (HH:mm)
- `sync_enabled`: nullable boolean
- `send_enabled`: nullable boolean

### `SettingsMail` API calls

- `PUT /api/settings/mail` — full payload (all fields required on backend)
- `POST /api/settings/mail/test-smtp` — partial payload (smtp + credentials)
- `POST /api/settings/mail/test-imap` — partial payload (imap + credentials)

Backend safeguard:

- `PUT /api/settings/mail` preserves the existing global signature when the payload contains `global_signature_html = null` and `global_signature_text = null`
- explicit signature clearing now requires `clear_signature = true`
- `POST /api/settings/mail/test-smtp` and `POST /api/settings/mail/test-imap` now return a precise aggregated `message` plus field-level `errors`

### `SettingsSignature` API calls

- `GET /api/settings` (to fetch current mail settings for merge)
- `PUT /api/settings/mail` (with signature fields + current mail settings merged)

### `GET /api/settings` response shape (for SettingsSignature merge)

- `general`: required object
- `mail`: required object
- `deliverability`: required object

Used by `SettingsSignature.vue` to merge the current mail configuration before calling `PUT /api/settings/mail`.

## Users

### Component

- `Users/Index`

### Current state

- route `/users` is exposed in V1
- no dedicated backend payload is sent yet for this page
- the current component falls back to its local default `users = []`
- this page should therefore be considered present but partial until a dedicated backend payload is added

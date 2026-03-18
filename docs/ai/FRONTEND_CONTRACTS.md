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
- `organizations`: required array
- `capabilities`: required object

### filters (Contacts)

- `search`: required string (empty string when not active)
- `status`: required enum `all|active|bounced|unsubscribed`
- `score`: required enum `all|engaged|interested|warm|cold|excluded`

### capabilities (Contacts)

- `canCreate`: required boolean
- `createEndpoint`: required string, currently `/api/contacts`

### organizations (Contacts)

- `id`: required integer
- `name`: required string

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
- `capabilities`: required object

### filters (Organizations)

- `search`: required string (empty string when not active)

### capabilities (Organizations)

- `canCreate`: required boolean
- `createEndpoint`: required string, currently `/api/organizations`

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

### Draft Actions (MailComposer / CampaignEditor)

- Schedule: `POST /api/drafts/{id}/schedule` — runs preflight, creates recipients, queues dispatch
- Send now: `POST /api/drafts/{id}/send-now` — same pipeline as schedule with `scheduledAt = now()`
- Unschedule: `POST /api/drafts/{id}/unschedule` — reverts draft to `draft` status
- Test send: `POST /api/drafts/{id}/test-send` — body `{ email: string }`, returns `{ success, message, driver, acceptedAt }`. Does not create recipients/messages.
- Preflight: `POST /api/drafts/{id}/preflight` — returns verification checks

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

### Actions

- Delete: `DELETE /api/templates/{id}` — permanently deletes template, detaches linked drafts
- Edit: `GET /templates/{id}/edit`
- Duplicate: `POST /api/templates/{id}/duplicate`

## Campaigns

### Component

- `Campaigns/Index`

### Props

- `campaigns`: required array
- `creationFlow`: required object

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

### creationFlow (Campaigns)

- `type`: required string, currently `draft_first`
- `entryHref`: required string, currently `/campaigns/create`
- `actionLabel`: required string, currently `Préparer une campagne`
- `helperText`: required string, explains that a campaign stays draft-backed internally but is prepared from the Campaigns module

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
- `threadId`: nullable integer

---

## Component Contracts

### Contacts/Show

**File:** `resources/js/Pages/Contacts/Show.vue`

**Props:**

- `contact`: required object
- `organizations`: required array

**contact**

- `id`: integer
- `firstName`: string
- `lastName`: string
- `fullName`: nullable string
- `title`: nullable string
- `phone`: nullable string
- `notes`: nullable string
- `status`: nullable string
- `organizationId`: nullable integer
- `organizationName`: nullable string
- `emails`: array of `{ id, email, isPrimary, optedOutAt, bounceStatus, lastSeenAt, canDelete }`
- `recentThreads`: array of `{ id, subject, lastActivityAt, lastDirection, replyReceived, autoReplyReceived }`
- `stats.threadCount`: integer
- `stats.recipientCount`: integer
- `stats.lastActivityAt`: nullable string

**API calls used by the page:**

- `PUT /api/contacts/{id}`
- `DELETE /api/contacts/{id}`
- `POST /api/contacts/{id}/emails`
- `DELETE /api/contacts/{id}/emails/{emailId}`

### Organizations/Show

**File:** `resources/js/Pages/Organizations/Show.vue`

**Props:**

- `organization`: required object

**organization**

- `id`: integer
- `name`: string
- `domain`: nullable string
- `website`: nullable string
- `notes`: nullable string
- `contactCount`: integer
- `sentCount`: integer
- `lastActivityAt`: nullable string
- `contacts`: array of `{ id, name, title, email }`
- `recentThreads`: array of `{ id, subject, contactName, lastActivityAt, lastDirection }`

**API calls used by the page:**

- `PUT /api/organizations/{id}`
- `DELETE /api/organizations/{id}`

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

### Campaigns/Create

**File:** `resources/js/Pages/Campaigns/Create.vue`

**Props:**

- `templates`: required array

**Behavior:**

- the operator enters through `/campaigns/create`
- `MailComposer` persists an internal draft first
- on `saved(draft)`, the page calls `POST /api/drafts/{draft}/campaign`
- on success, the UI redirects to `/campaigns/{campaign}`

### Campaigns/Show

**File:** `resources/js/Pages/Campaigns/Show.vue`

**Props:**

- `campaign`: required object
- `templates`: required array

**campaign**

- same base shape as `Campaigns/Index > campaigns[]`
- `draft`: nullable full draft object
- `recipients`: array of `{ id, email, status, contactName, organization, scheduledFor, lastEventAt }`

**API calls used by the page:**

- `DELETE /api/campaigns/{id}`
- edit mode now uses `CampaignEditor` (replaces MailComposer in campaign context)

---

## CampaignEditor

**File:** `resources/js/Components/Campaigns/CampaignEditor.vue`

**Props:**

- `campaignId`: nullable integer — existing campaign ID (null on first create)
- `draftId`: nullable integer — existing draft ID (null on first create)
- `initialName`: string — campaign name
- `initialSubject`: string
- `initialTextBody`: string
- `initialHtmlBody`: string
- `initialTemplateId`: nullable integer
- `initialRecipients`: array — existing recipients in autosave format
- `initialAudiences`: nullable object — pre-loaded audience data (skips API call if provided)
- `templates`: array

**Emits:**

- `autosaved(data)` — called after each successful autosave; `data = { draft, campaign }`
- `scheduled()` — called after campaign is scheduled
- `close()` — not used internally, available for parent

**Autosave behavior:**

- debounced 1.5s after each form change
- calls `POST /api/campaigns/autosave`
- autosave status: `idle | pending | saving | saved | error`
- status displayed discretely in the status bar (no primary save button)
- tracks `campaignId`, `draftId`, `expectedUpdatedAt` across saves
- preflight and schedule require a saved draft (wait for autosave)

**Autosave payload** (`POST /api/campaigns/autosave`):

- `type`: always `'bulk'`
- `campaignId`: nullable integer
- `draftId`: nullable integer
- `name`: string
- `subject`: string
- `htmlBody`: nullable string
- `textBody`: nullable string
- `templateId`: nullable integer
- `expectedUpdatedAt`: nullable ISO-8601 string (conflict guard)
- `recipients[]`: each with `{ email, contactId, contactEmailId, organizationId, organizationName, name }`

**Autosave response:**

- `draft`: serialized draft object
- `campaign`: serialized campaign object (includes `id`, `lastEditedAt`, `updatedAt`)

---

## CampaignAudiencePicker

**File:** `resources/js/Components/Campaigns/CampaignAudiencePicker.vue`

**Props:**

- `modelValue`: array — selected recipients in autosave format
- `initialAudiences`: nullable object — pre-loaded `{ contacts[], organizations[], recentImports[] }`

**Emits:** `update:modelValue`

**API call (when `initialAudiences` is null):**

- `GET /api/campaigns/audiences` → `{ contacts[], organizations[], recentImports[] }`

**contacts[]:**

- `contactId`: integer
- `contactEmailId`: nullable integer
- `organizationId`: nullable integer
- `organizationName`: nullable string
- `email`: nullable string
- `name`: string
- `jobTitle`: nullable string

**organizations[]:**

- `organizationId`: integer
- `organizationName`: string
- `domain`: nullable string
- `contactCount`: integer
- `contacts[]`: audience contacts (same shape)

**recentImports[]:**

- `id`: integer
- `sourceName`: string
- `sourceType`: string
- `importedAt`: nullable ISO-8601 string
- `contactCount`: integer
- `contacts[]`: audience contacts (same shape)

**UX rules:**

- contacts without `email` are shown but are disabled (cannot be selected)
- contacts without `organization_id` are excluded from the API result entirely
- empty state shows CTA to add contacts or import

---

## Contacts/Import

**File:** `resources/js/Pages/Contacts/Import.vue`

**Route:** `GET /contacts/imports`

**Props:** none — fully client-side

**Steps:**

1. Upload (idle): show template download CTA + file drop zone (CSV/XLSX)
2. Preview: show summary + row table after `POST /api/contacts/imports/preview`
3. Result: show batch summary after `POST /api/contacts/imports`

**API calls:**

- Template download: `GET /api/contacts/imports/template` (streamed CSV file)
- Preview: `POST /api/contacts/imports/preview` (multipart, field `file`)
- Confirm: `POST /api/contacts/imports` with `{ previewToken }`

**Preview response** (`POST /api/contacts/imports/preview`):

`data.preview`:

- `previewToken`: string (UUID, 1-hour TTL)
- `sourceName`: string
- `sourceType`: string
- `summary.totalRows`: integer
- `summary.validRows`: integer
- `summary.invalidRows`: integer
- `summary.duplicateExistingRows`: integer
- `summary.duplicateFileRows`: integer
- `summary.organizationMatches`: integer
- `summary.organizationCreates`: integer
- `rows[]`: each with `{ lineNumber, status('valid'|'invalid'|'duplicate_existing'|'duplicate_in_file'), primaryEmail, name, organization.name, organization.action, reason }`

**Import response** (`POST /api/contacts/imports`):

- `message`: string
- `batch`: batch summary object
- `summary.importedRows`: integer
- `summary.skippedRows`: integer
- `summary.duplicateExistingRows`: integer
- `summary.invalidRows`: integer
- `rows[]`: each with `resultStatus('imported'|'skipped'|'duplicate_existing')`, `resultMessage`, `lineNumber`, `primaryEmail`

---

## SettingsDeliverability (updated)

**File:** `resources/js/Pages/Settings/Sections/SettingsDeliverability.vue`

**Props:** `settings` — the full deliverability payload from `SettingsPageDataService::page()`

**Full deliverability payload shape:**

- `domain`: nullable string
- `dkimSelectors`: array of strings
- `refreshEndpoint`: string (always `/api/settings/deliverability/checks/refresh`)
- `spfValid`: boolean (shortcut)
- `dkimValid`: boolean (shortcut)
- `dmarcValid`: boolean (shortcut)
- `trackOpens`: boolean
- `trackClicks`: boolean
- `maxLinks`: integer
- `maxImages`: integer
- `maxHtmlSizeKb`: integer
- `maxAttachmentSizeMb`: integer
- `checks.spf`: DeliverabilityCheck
- `checks.dkim`: DeliverabilityCheck
- `checks.dmarc`: DeliverabilityCheck

**DeliverabilityCheck:**

- `status`: enum `pass|warning|fail|not_detected`
- `detected_value`: nullable string
- `checked_at`: nullable ISO-8601 string
- `diagnostic_message`: string
- `logs[]`: each `{ level('info'|'warning'|'error'), message, context, ts(ISO-8601) }`

**Retest API:**

- `POST /api/settings/deliverability/checks/refresh` with `{ mechanisms: ['spf','dkim','dmarc'] }`
- Response: `{ message, deliverability }` — `deliverability` has same shape as above

**UI states covered:**

- `pass` / `warning` / `fail` / `not_detected` for each mechanism
- retesting loading state
- retest error message
- logs panel (collapsible, per mechanism)
- empty logs state (logs button hidden)

### Threads/Show

**File:** `resources/js/Pages/Threads/Show.vue`

**Props:**

- `thread`: required object

**thread**

- `id`: integer
- `subject`: string
- `contactName`: nullable string
- `organization`: nullable string
- `replyReceived`: boolean
- `autoReplyReceived`: boolean
- `lastDirection`: `in|out`
- `lastActivityAt`: nullable ISO-8601 string
- `messages`: array of `{ id, direction, fromEmail, toEmails, subject, classification, messageIdHeader, inReplyToHeader, referencesHeader, sentAt, receivedAt, hasAttachments, attachmentCount }`

---

## Action visibility status (V1)

| Screen    | Action                | Status               | Reason                                                                                                    |
| --------- | --------------------- | -------------------- | --------------------------------------------------------------------------------------------------------- |
| Mails     | Voir                  | Active (conditional) | Opens `/threads/{threadId}` when a materialized thread exists; otherwise disabled with an explicit reason |
| Campaigns | Détails               | Active               | Opens `/campaigns/{id}`                                                                                   |
| Campaigns | Préparer une campagne | Active               | Opens `/campaigns/create`; draft stays internal                                                           |
| Templates | Nouveau modèle        | Active               | Wired to TemplateEditor create mode                                                                       |
| Templates | Éditer                | Active               | Wired to TemplateEditor edit mode                                                                         |
| Templates | Dupliquer             | Active               | Wired to `POST /api/templates/{id}/duplicate`                                                             |
| Templates | Archiver/Activer      | Active               | Wired to `POST /api/templates/{id}/archive\|activate`                                                     |
| Drafts    | Éditer                | Active               | Loads draft via `GET /api/drafts/{id}` → MailComposer                                                     |
| Drafts    | Dupliquer             | Active               | Wired to `POST /api/drafts/{id}/duplicate`                                                                |
| Drafts    | Supprimer             | Active               | Wired to `DELETE /api/drafts/{id}`                                                                        |
| Drafts    | Suppression de masse  | Active               | Wired to `DELETE /api/drafts` with `ids[]`                                                                |
| Drafts    | Déprogrammer          | Active (conditional) | Only shown when `status === 'scheduled'`                                                                  |
| Composer  | Sauvegarder brouillon | Active               | `POST /api/drafts` or `PUT /api/drafts/{id}`                                                              |
| Composer  | Vérifier (preflight)  | Active               | Requires saved draft first (disabled otherwise)                                                           |
| Composer  | Planifier             | Active (conditional) | Only shown when `preflight.ok === true`                                                                   |
| Composer  | Aperçu HTML           | Active               | Sandboxed iframe render toggle on HTML body field                                                         |
| Composer  | Pièces jointes        | Not implemented      | Attachment upload API not exposed in V1                                                                   |
| Contacts  | Ajouter un contact    | Active               | Wired to `POST /api/contacts`                                                                             |
| Contacts  | Fiche                 | Active               | Opens `/contacts/{id}`                                                                                    |
| Contacts  | Historique            | Active               | Jumps to `/contacts/{id}#historique`                                                                      |
| Contacts  | Modifier              | Active               | Wired to `PUT /api/contacts/{id}`                                                                         |
| Contacts  | Supprimer             | Active               | Wired to `DELETE /api/contacts/{id}`                                                                      |
| Contacts  | Ajouter un e-mail     | Active               | Wired to `POST /api/contacts/{id}/emails`                                                                 |
| Contacts  | Supprimer un e-mail   | Active (conditional) | Wired to `DELETE /api/contacts/{id}/emails/{emailId}` when `canDelete = true`                             |
| Orgs      | Ajouter organisation  | Active               | Wired to `POST /api/organizations`                                                                        |
| Orgs      | Fiche                 | Active               | Opens `/organizations/{id}`                                                                               |
| Orgs      | Historique            | Active               | Jumps to `/organizations/{id}#historique`                                                                 |
| Orgs      | Modifier              | Active               | Wired to `PUT /api/organizations/{id}`                                                                    |
| Orgs      | Supprimer             | Active               | Wired to `DELETE /api/organizations/{id}`                                                                 |
| Settings  | Enregistrer           | Active               | `PUT /api/settings/mail` — full mail settings payload                                                     |
| Settings  | Tester SMTP           | Active               | `POST /api/settings/mail/test-smtp`                                                                       |
| Settings  | Tester IMAP           | Active               | `POST /api/settings/mail/test-imap`                                                                       |
| Settings  | Signer. (signature)   | Active               | Fetches current settings, then `PUT /api/settings/mail`                                                   |

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
- the `message` string is intentionally French and operator-facing; the frontend can render `errors` per field without rewording the backend validation

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

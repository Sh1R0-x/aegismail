# FRONTEND CONTRACTS

## Purpose

This document freezes the Inertia payload shape exposed by Laravel to the current frontend pages.

Date format used by the current backend:

- ISO 8601 strings (e.g. `2026-03-20T09:30:00+01:00`)
- nullable dates are returned as `null`
- Frontend formats all dates using `formatDateFR()` from `resources/js/Utils/formatDate.js`, producing `dd/mm/yyyy - HHhMM` (e.g. `20/03/2026 - 09h30`)

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
- `healthStatus`: required enum `good|degraded|critical|unknown`
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
- `importExportModule`: required object
- `recentImports`: required array

### filters (Contacts)

- `search`: required string (empty string when not active)
- `status`: required enum `all|active|bounced|unsubscribed`
- `score`: required enum `all|engaged|interested|warm|cold|excluded`

### capabilities (Contacts)

- `canCreate`: required boolean
- `createEndpoint`: required string, currently `/api/contacts`
- `organizationRequired`: required boolean
- `imports.moduleKey`: required string, currently `contacts_organizations`
- `imports.moduleEndpoint`: required string, currently `/api/import-export`
- `imports.pagePath`: required string, currently `/contacts/imports`
- `imports.canImport`: required boolean
- `imports.canExport`: required boolean
- `imports.exportEndpoint`: required string, currently `/api/import-export/export`
- `imports.previewEndpoint`: required string, currently `/api/contacts/imports/preview` (legacy-compatible alias)
- `imports.confirmEndpoint`: required string, currently `/api/contacts/imports`
- `imports.templateEndpoint`: required string, currently `/api/contacts/imports/template`

### importExportModule

- `moduleKey`: required string
- `pagePath`: required string
- `previewEndpoint`: required string
- `confirmEndpoint`: required string
- `templateEndpoint`: required string
- `exportEndpoint`: required string
- `acceptedFileTypes`: required array
- `templateColumns`: required array
- `acceptedAliases`: required object
- `recentImports`: required array

### organizations (Contacts)

- `id`: required integer
- `name`: required string

### contacts[]

- `id`: required integer
- `firstName`: required string
- `lastName`: required string
- `fullName`: nullable string
- `title`: nullable string
- `organization`: nullable string
- `organizationId`: nullable integer
- `organizationName`: nullable string
- `email`: required string
- `linkedinUrl`: nullable string
- `phone`: nullable string
- `phoneLandline`: nullable string
- `phoneMobile`: nullable string
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

- `id`: required integer (or string `tracking-{id}` for tracking events)
- `threadId`: nullable integer
- `title`: required string
- `description`: nullable string
- `status`: required string — for outbound messages, uses `mail_recipients.status` as source of truth (e.g. `queued|sending|sent|failed|opened|clicked|replied|cancelled`). For inbound messages, derived from classification (e.g. `replied|auto_replied|soft_bounced|hard_bounced|delivered_if_known`). For orphan outbound messages without a recipient, falls back to `sent` if `sent_at` is set, otherwise `queued`.
- `direction`: required enum `outbound|inbound`
- `isAutoReply`: required boolean
- `isBounce`: required boolean
- `date`: nullable ISO-8601 string

## Mails

### Component

- `Mails/Index`

### Props

- `recipients`: required array
- `drafts`: required array
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
- `contactName`: nullable string (resolved from contact relation)
- `organization`: nullable string (resolved from contact.organization)
- `subject`: required string
- `status`: required string (any frozen status value)
- `type`: required enum `single|multiple`
- `sentAt`: nullable string (ISO 8601)
- `campaignId`: nullable integer
- `threadId`: nullable integer

### drafts[]

Same shape as `DraftService::serializeListItem()`:

- `id`: required integer
- `subject`: nullable string
- `recipientCount`: required integer
- `type`: required enum `single|multiple`
- `status`: required enum `draft|scheduled`
- `scheduledAt`: nullable string (ISO 8601)
- `updatedAt`: required string (ISO 8601)

### UI tabs

The Mails page uses a 3-tab layout:

- **Envoyés** — shows `recipients[]` with status filter and search
- **Brouillons** — shows `drafts[]` where `status === 'draft'`, with bulk select, edit/duplicate/delete
- **Programmés** — shows `drafts[]` where `status === 'scheduled'`, with edit/unschedule

`/drafts` now redirects (302) to `/mails?tab=drafts`. The initial tab is read from the `tab` URL query param.

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
- `primaryEmail`: nullable string
- `title`: nullable string
- `phone`: nullable string
- `phoneLandline`: nullable string
- `phoneMobile`: nullable string
- `linkedinUrl`: nullable string
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
- if `textBody` is empty but `htmlBody` exists, backend generates a plain-text body at dispatch time

Additional blocking codes now exposed by backend preflight:

- `link_requires_public_base` — link has a relative URL but no public base URL is configured → guide to Settings › Deliverability
- `link_not_https` — link URL is not HTTPS → guide to Settings › Deliverability
- `link_not_public` — link URL is a local/private/non-routable host → guide to Settings › Deliverability
- `image_requires_public_base` — same as above but for remote images
- `image_not_https` — same as above but for remote images
- `image_not_public` — same as above but for remote images
- `tracking_base_url_invalid` — tracking enabled but no valid public HTTPS base URL exists → guide to Settings › Deliverability
- `bulk_unsubscribe_unavailable` — campaign bulk send requires a public HTTPS base URL for List-Unsubscribe header → guide to Settings › Deliverability

Frontend handling:

- All error codes include a backend-translated `message` string (French) — display as-is
- URL-related codes (`link_*`, `image_*`, `tracking_base_url_invalid`, `bulk_unsubscribe_unavailable`) additionally show a "→ Corriger dans Réglages › Délivrabilité" guide note in the UI
- `hasTextVersion` now reflects the truly final text/plain (after backend synthesis from HTML if needed); the frontend shows "Présente — inclus dans MIME" when true

### SettingsDeliverability

**File:** `resources/js/Pages/Settings/Sections/SettingsDeliverability.vue`

**No Inertia props** — fetches its own data on mount via `GET /api/settings` (`deliverability` key).

**Backend deliverability payload** (from `GET /api/settings` or `PUT /api/settings/deliverability` response):

- `public_base_url`: nullable string — the configured raw value
- `tracking_base_url`: nullable string — the configured raw value
- `publicBaseUrl`: nullable string — the resolved (validated) public base URL (null if invalid)
- `trackingBaseUrl`: nullable string — the resolved tracking base URL (null if invalid)
- `publicBaseUrlStatus`: enum `valid|invalid|missing` — resolved status
- `trackingBaseUrlStatus`: enum `valid|invalid|missing` — resolved status
- `publicBaseUrlIssue`: nullable string — issue key (`public_base_url_not_https | public_base_url_not_public | public_base_url_missing`)
- `trackingBaseUrlIssue`: nullable string — issue key (same enum)
- `trackOpens`: boolean
- `trackClicks`: boolean
- `maxLinks`: integer — warning threshold for link count
- `maxImages`: integer — warning threshold for remote image count
- `maxHtmlSizeKb`: integer — warning threshold for HTML size
- `maxAttachmentSizeMb`: integer — warning threshold for attachment size

**Save payload** (`PUT /api/settings/deliverability`) — all required:

- `tracking_opens_enabled`: boolean
- `tracking_clicks_enabled`: boolean
- `max_links_warning_threshold`: integer
- `max_remote_images_warning_threshold`: integer
- `html_size_warning_kb`: integer
- `attachment_size_warning_mb`: integer
- `public_base_url`: nullable string
- `tracking_base_url`: nullable string

**UI states covered:**

- Loading skeleton while fetching
- Status badge per URL: `Valide` (green) / `Non configurée` (grey) / `Invalide` (red)
- Resolved URL shown as clickable link when status is valid
- Issue alert shown below each URL field when issue is non-null
- Save success/error banner (auto-dismiss 5s on success)

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
- `recipients`: array of `{ id, email, status, contactName, organization, scheduledFor, lastEventAt, lastSentAt, lastSentSubject }`

**Recipient search & filters (client-side):**

- `recipientSearch`: text input filtering by email, contactName, organization (case-insensitive)
- `filterStatus`: select populated from unique statuses present in recipients
- `filterOrganization`: select populated from unique organization names
- `filterDomain`: select populated from unique email domains (after `@`)
- All filters are composable (AND logic) and can be cleared with "Effacer les filtres" button
- Counter `"N / total"` shows filtered vs. total count in the section header

**API calls used by the page:**

- `DELETE /api/campaigns/{id}`
- `POST /api/campaigns/{id}/clone` — creates a new draft campaign; on success redirects to `/campaigns/{newId}?cloned=1`
- edit mode now uses `CampaignEditor` (replaces MailComposer in campaign context)

**Clone UX:**

- "Cloner" button placed in header-actions between "Modifier" and "Déprogrammer"
- On success: `router.visit('/campaigns/{newId}', { data: { cloned: '1' } })` redirects to the new campaign
- On arrival at the new campaign (`?cloned=1`): `onMounted` reads `URLSearchParams`, sets a success banner `"Campagne clonée avec succès — elle repart en brouillon, prête à être éditée."`, then cleans the URL via `window.history.replaceState`
- Loading state: `cloning` ref disables the button during the API call
- Error state: sets `banner.value = { type: 'error', message }` inline

**Campaigns/Index clone action:**

- "Cloner" button added in the Actions column per row (before "Détails")
- `cloningId` ref tracks which row is in-flight (prevents double-click and multiple concurrent clones)
- On success: same redirect with `{ data: { cloned: '1' } }`
- Error state: `cloneError` ref drives a top-of-page error banner

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
2. Preview: show summary + row table after `POST /api/import-export/preview` or the legacy alias `POST /api/contacts/imports/preview`
3. Result: show batch summary after `POST /api/import-export/confirm` or the legacy alias `POST /api/contacts/imports`

**API calls:**

- Module meta: `GET /api/import-export`
- Template download: `GET /api/import-export/template` or legacy `GET /api/contacts/imports/template`
- Export current data: `GET /api/import-export/export`
- Preview: `POST /api/import-export/preview` (multipart, field `file`)
- Confirm: `POST /api/import-export/confirm` with `{ previewToken }`

**Preview response** (`POST /api/import-export/preview`):

`data.preview`:

- `moduleKey`: string (`contacts_organizations`)
- `previewToken`: string (UUID, 1-hour TTL)
- `sourceName`: string
- `sourceType`: string
- `templateColumns[]`: string headers in stable import/export order
- `detectedColumns[]`: `{ index, sourceHeader, normalizedHeader, field, label, retained }`
- `mapping`: object keyed by backend contract field (`organizationName`, `firstName`, `lastName`, `primaryEmail`, `linkedinUrl`, `phoneLandline`, `phoneMobile`, ...)
- `sampleRows[]`: raw row samples echoed with original CSV headers
- `persistedFields[]`: `{ field, label, persistsTo }`
- `summary.totalRows`: integer
- `summary.validRows`: integer
- `summary.writeRows`: integer
- `summary.createRows`: integer
- `summary.updateRows`: integer
- `summary.unchangedRows`: integer
- `summary.skipRows`: integer
- `summary.errorRows`: integer
- `summary.invalidRows`: integer
- `summary.duplicateExistingRows`: integer
- `summary.duplicateFileRows`: integer
- `summary.organizationMatches`: integer
- `summary.organizationCreates`: integer
- `summary.organizationUpdates`: integer
- `summary.contactCreates`: integer
- `summary.contactUpdates`: integer
- `summary.contactUnchanged`: integer
- `counters`: `{ create, update, unchanged, skip, error }`
- `errors[]`: aggregated blocking issue groups `{ code, message, count, lineNumbers[] }`
- `warnings[]`: aggregated warning groups `{ code, message, count, lineNumbers[] }`
- `conflicts[]`: aggregated conflict groups `{ code, message, count, lineNumbers[] }`
- `organizationSummary`: `{ matchedExistingCount, createdCount, keptExistingCount, updatedCount, missingCount }`
- `contactSummary`: `{ createdCount, updatedCount, unchangedCount }`
- `rows[]`: each with:
    - `lineNumber`
    - `status('valid'|'unchanged'|'invalid'|'duplicate_in_file')`
    - `action('create'|'update'|'unchanged'|'skip'|'error')`
    - `plannedActions.organization{ code, label }`
    - `plannedActions.contact{ code, label }`
    - `organization.action('create'|'update'|'reuse'|'keep_existing'|'preserve_existing'|'missing'|'ambiguous')`
    - `organization.writeAction('create'|'update'|'unchanged')`
    - `organization.willWrite`
    - `organization.matchStrategy`
    - `organization.changes[]`
    - `contact.action('create'|'update'|'unchanged')`
    - `contact.willWrite`
    - `contact.matchStrategy('exact_email'|'exact_email_missing')`
    - `contact.changes[]`
    - `primaryEmail`
    - `name`
    - `organizationName`
    - `linkedinUrl`
    - `phoneLandline`
    - `phoneMobile`
    - `reason`
    - `reasonCode`
    - `normalized{}`
    - `persistedFields[]`
    - `errors[]`
    - `warnings[]`
    - `conflicts[]`
    - `existingContact?`

**Import response** (`POST /api/import-export/confirm`):

- `moduleKey`: string
- `message`: string
- `batch`: batch summary object
- `summary.importedRows`: integer
- `summary.createdRows`: integer
- `summary.updatedRows`: integer
- `summary.unchangedRows`: integer
- `summary.skippedRows`: integer
- `summary.errorRows`: integer
- `summary.duplicateExistingRows`: integer
- `summary.invalidRows`: integer
- `rows[]`: each with `resultStatus('imported'|'skipped'|'error')`, `resultAction('create'|'update'|'unchanged'|'skip'|'error')`, `resultMessage`, `lineNumber`, `primaryEmail`

---

### ImportExport/Index

**File:** `resources/js/Pages/ImportExport/Index.vue`

**Route:** `GET /import-export`

**Props:** none — fully client-side

**Navigation:** dedicated entry "Import / Export" in the main sidebar, between Activity and Settings

**Steps:**

1. Upload (idle): show export section (template + data) + file drop zone (CSV/XLSX)
2. Preview: show column mapping + global counters + org/contact breakdown + diff table with per-row action filter + errors/warnings
3. Result: show import summary with counters + non-imported rows table + navigation links

**API calls:**

- Template download: `GET /api/import-export/template`
- Export current data: `GET /api/import-export/export`
- Preview: `POST /api/import-export/preview` (multipart, field `file`)
- Confirm: `POST /api/import-export/confirm` with `{ previewToken }`

**Preview response consumed by the page:**

Same shape as documented in the Contacts/Import section above. Key fields displayed:

- `sourceName`, `rows.length` in file info header
- `detectedColumns[]` with retained/non-retained visual distinction
- `errors[]`, `warnings[]` as blocking/warning banners
- `counters.create`, `counters.update`, `counters.unchanged`, `counters.skip`, `counters.error` as counter cards
- `organizationSummary` and `contactSummary` as breakdown cards
- `rows[]` with per-row: `lineNumber`, `action`, `organizationName`, `organization.action`, `organization.changes[]`, `name`, `contact.action`, `contact.changes[]`, `primaryEmail`, `linkedinUrl`, `phoneLandline`, `phoneMobile`, `plannedActions`, `errors[]`, `warnings[]`, `reason`

**Diff filter:** rows can be filtered by action type (`all|create|update|unchanged|skip|error`)

**Confirm response consumed:** same shape as Contacts/Import confirm response

**UI states covered:**

- No file selected (upload step with export section)
- Wrong file format (client-side validation error)
- CSV analysis in progress (loading spinner)
- Preview empty (0 writable rows)
- Preview with warnings only
- Preview with blocking errors
- Preview with create only
- Preview with update only
- Preview with unchanged only
- Preview mixed (create/update/unchanged/skip/error)
- Import in progress (confirming state)
- Import successful
- Import partial (some rows not imported)
- Import impossible — previewToken invalid or already consumed
- Export buttons always visible in upload step
- Back to upload / new file reset

---

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
- `messages`: array of `{ id, direction, fromEmail, toEmails, subject, classification, messageIdHeader, inReplyToHeader, referencesHeader, sentAt, receivedAt, hasAttachments, attachmentCount, htmlBody, textBody }`

The thread detail page now displays the actual content of each message:

- `htmlBody`: nullable string — HTML content rendered via `v-html` in a prose container
- `textBody`: nullable string — plain-text fallback displayed in a `<pre>` block
- Messages are expandable (toggle button per message)

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
- `settings.cadence`: required object
- `settings.scoring`: required object
- `settings.signature.global_signature_html`: nullable string
- `settings.signature.global_signature_text`: nullable string

V1 note:

- the Settings page no longer exposes a `Délivrabilité` section
- SPF / DKIM / DMARC diagnostics are not rendered in the UI
- DNS configuration remains an external prerequisite

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

Used by `SettingsSignature.vue` to merge the current mail configuration before calling `PUT /api/settings/mail`.
`GET /api/settings` may still include additional internal keys such as `deliverability`, but the Settings UI does not render a dedicated deliverability section in V1.

## Users

### Component

- `Users/Index`

### Current state

- route `/users` is exposed in V1
- no dedicated backend payload is sent yet for this page
- the current component falls back to its local default `users = []`
- this page should therefore be considered present but partial until a dedicated backend payload is added

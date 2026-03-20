# BACKEND CONTRACTS

## Scope

This document freezes the backend contracts exposed by Laravel to the current frontend pages in V1.

Rules kept:

- one OVH mailbox identity only
- mailbox provider = `ovh_mx_plan`
- outbound providers limited to `ovh_mx_plan|smtp2go`
- no Gmail logic
- no Google APIs
- no generic multi-provider abstraction beyond OVH MX Plan + SMTP2GO
- one sending queue for all outgoing mail

## Inertia shared props

All Inertia pages receive these shared props via `HandleInertiaRequests`:

- `auth.user`: authenticated user object
- `gatewayDriver`: `string` — current mail gateway driver (`'stub'` or `'http'`). Used by `CrmLayout` to show a global warning banner when running in stub mode.

## Inertia routes

- `GET /dashboard` -> `Dashboard`
- `GET /t/o/{token}.gif` -> transparent 1x1 open-tracking pixel
- `GET /t/c/{token}` -> click-tracking redirect
- `GET /u/{token}` -> public one-click unsubscribe endpoint
- `POST /u/{token}` -> public one-click unsubscribe endpoint, CSRF-exempt for mailbox client compatibility
- `GET /drafts` -> **302 redirect to `/mails?tab=drafts`** — drafts are now integrated into the unified Mails hub
- `GET /mails` -> `Mails/Index` — unified hub. Payload from `ComposerPageDataService::mails()` includes `recipients[]`, `drafts[]`, `stats{}`, `templates[]`, `filters{}`
- `GET /contacts` -> `Contacts/Index`
- `GET /contacts/{contact}` -> `Contacts/Show`
- `GET /organizations` -> `Organizations/Index`
- `GET /organizations/{organization}` -> `Organizations/Show`
- `GET /templates` -> `Templates/Index`
- `GET /campaigns` -> `Campaigns/Index`
- `GET /campaigns/create` -> `Campaigns/Create`
- `GET /campaigns/{campaign}` -> `Campaigns/Show`
- `GET /threads/{thread}` -> `Threads/Show`
- `GET /activity` -> `Activity/Index`
- `GET /settings` -> `Settings/Index`
- `GET /users` -> `Users/Index` — route present in V1, but no dedicated backend payload yet; the current page relies on its local default `users = []`

## API routes

### Templates

- `GET /api/templates`
- `POST /api/templates`
- `PUT /api/templates/{template}`
- `POST /api/templates/{template}/duplicate`
- `DELETE /api/templates/{template}` — permanently deletes the template; detaches linked drafts first

### Drafts

- `GET /api/drafts`
- `GET /api/drafts/{draft}`
- `POST /api/drafts`
- `PUT /api/drafts/{draft}`
- `DELETE /api/drafts/{draft}`
- `POST /api/drafts/{draft}/duplicate`
- `POST /api/drafts/{draft}/preflight`
- `POST /api/drafts/{draft}/schedule` — returns: `{ ok, message, campaign, driver }`
- `POST /api/drafts/{draft}/unschedule`
- `POST /api/drafts/{draft}/send-now` — schedules for immediate dispatch (runs preflight, same pipeline as schedule). Returns: `{ ok, message, campaign, driver }`
- `POST /api/drafts/{draft}/test-send` — sends a test email via the gateway client without creating recipients/messages. Body: `{ email: string }`. Returns: `{ success, message, provider, providerLabel, driver, acceptedAt }`
- `POST /api/drafts/{draft}/campaign`
- `POST /api/drafts/bulk-delete` — bulk-delete drafts via POST (avoids DELETE body issues). Body: `{ ids: int[] }`. Same logic as `DELETE /api/drafts`
- `POST /api/drafts/{draft}/attachments` — upload a file attachment to a draft. Multipart form: `{ file: UploadedFile }`. Max 10 MB. Returns: `{ attachment: { id, name, size, mimeType } }`
- `DELETE /api/drafts/{draft}/attachments/{attachment}` — remove an attachment from a draft and delete the stored file

### Campaigns

- `GET /api/campaigns`
- `GET /api/campaigns?includeDeleted=1` — includes logically deleted campaigns in the response for audit/admin use
- `GET /api/campaigns/audiences`
- `POST /api/campaigns/autosave`
- `POST /api/campaigns/{campaign}/clone` — clones an existing campaign (including completed ones) into a new draft campaign. Returns: `{ campaign, message }`
- `DELETE /api/campaigns/{campaign}` — returns `{ message, deletionMode }` where `deletionMode` is `hard` or `soft`

### Threads

- `GET /api/threads`
- `GET /api/threads/{thread}`

### CRM

- `GET /api/import-export`
- `GET /api/import-export/template`
- `GET /api/import-export/export`
- `POST /api/import-export/preview`
- `POST /api/import-export/confirm`
- `POST /api/contacts`
- `GET /api/contacts/search` — lightweight contact search for composer autocomplete. Query: `?q=string` (min 2 chars). Returns: `{ results: [{ contactId, name, email, contactEmailId, organizationId, organizationName }] }`. Max 20 results.
- `GET /api/contacts/imports/template`
- `GET /api/contacts/imports/export`
- `POST /api/contacts/imports/preview`
- `POST /api/contacts/imports`
- `GET /api/contacts/{contact}`
- `PUT /api/contacts/{contact}`
- `DELETE /api/contacts/{contact}`
- `POST /api/contacts/{contact}/emails`
- `DELETE /api/contacts/{contact}/emails/{contactEmail}`
- `POST /api/organizations`
- `GET /api/organizations/{organization}`
- `PUT /api/organizations/{organization}`
- `DELETE /api/organizations/{organization}`

### Settings

- `GET /api/settings`
- `PUT /api/settings/general`
- `PUT /api/settings/mail`
- `PUT /api/settings/deliverability`
- `POST /api/settings/mail/test-smtp`
- `POST /api/settings/mail/test-imap`

## Campaign clone flow

`POST /api/campaigns/{campaign}/clone` creates a new independent campaign from any existing campaign (including completed/sent ones).

### Fields copied from source

- `name` with `(copie)` suffix
- `mode` (single/bulk)
- `mailbox_account_id`
- `user_id`
- `send_window_json`
- `throttling_json`
- Draft content: `subject`, `html_body`, `text_body`, `signature_snapshot`, `template_id`
- Draft `payload_json` (audience/recipients logic)
- Draft attachments (new records referencing same storage paths)

### Fields reset (not copied)

- `status` → `draft`
- `started_at` → null
- `completed_at` → null
- `scheduled_at` → null (on the new draft)
- `last_edited_at` → now()
- No `mail_recipients` copied — regenerated from audience logic on schedule via preflight
- No `mail_events` copied
- No `mail_messages` copied
- No execution metrics, opens, clicks, replies, bounces, send dates

### Response shape

```json
{
    "campaign": {
        /* standard campaign serialize shape */
    },
    "message": "Campagne clonée avec succès."
}
```

HTTP 201 on success. HTTP 404 if campaign not found.

## Outbound flow used in V1

`POST /api/drafts/{draft}/schedule` now performs:

- draft validation
- preflight
- campaign creation or update
- deliverable `mail_recipients` creation
- per-recipient `mail_threads` creation
- per-recipient `mail_messages` creation before SMTP dispatch
- final outbound body preparation from authored content + global signature snapshot
- guaranteed multipart-compatible output with both `text/plain` and `text/html`:
    - text body is synthesized from HTML when missing
    - HTML body is synthesized from text when missing
- relative links and remote images are normalized against the configured public HTTPS email base before dispatch
- dispatch is blocked when a public URL cannot be resolved safely
- click-tracking link rewrite in HTML + text bodies when enabled
- 1x1 open-tracking pixel injection in HTML bodies when enabled
- bulk sends add `List-Unsubscribe`, `List-Unsubscribe-Post`, `Precedence: bulk` and `X-Auto-Response-Suppress`
- queue placement on the unique queue `mail-outbound`
- delayed dispatch according to cadence settings
- persistence of `sent` or `failed` outcomes in `mail_messages`, `mail_recipients`, `mail_events`

`DispatchMailMessageJob` is the Laravel → mail-gateway boundary for outbound sends.

Additional boundary rule:

- internal metadata under `headers_json.tracking`, `headers_json.gateway` and `headers_json.gateway_error` is persisted in Laravel but must not be forwarded as raw SMTP headers by the Node gateway

Important runtime rule:

- campaign state is based on the first effective scheduled slot after send-window and ceiling adjustments, not only on the raw `scheduledAt` request value
- campaign creation stays technically `draft-first` in V1
- operator entry is now `/campaigns/create`, not `/drafts`
- there is still no standalone `POST /api/campaigns` create endpoint for immediate dispatch; Laravel keeps an internal draft layer and now also exposes `POST /api/campaigns/autosave` to upsert that internal draft+campaign pair without a mandatory manual "save draft" action

## Public email URL source of truth in V1

- public email base resolution order:
    - persisted `settings.deliverability.public_base_url`
    - config default `MAIL_PUBLIC_BASE_URL`
    - `APP_URL`
- tracking/unsubscribe base resolution order:
    - persisted `settings.deliverability.tracking_base_url`
    - config default `MAIL_TRACKING_BASE_URL`
    - resolved public email base
- every resolved outbound base URL must be absolute, HTTPS and publicly reachable from the outside
- localhost, loopback, private IPs, reserved IPs and reserved local hostnames/TLDs are rejected
- the same rule applies to open pixels, click URLs, unsubscribe URLs, normalized HTML links and normalized remote image URLs

## Tracking flow used in V1

- open token = outbound `mail_messages.aegis_tracking_id`
- click token = `{aegis_tracking_id}.{link_index}.{signature}`
- click metadata is persisted in `mail_messages.headers_json.tracking.clicks[]`
- open metadata is persisted in `mail_messages.headers_json.tracking.open`
- `GET /t/o/{token}.gif` always returns a transparent GIF and, on first valid hit:
    - sets `mail_messages.opened_first_at`
    - upgrades `mail_recipients.status` from `sent|delivered_if_known` to `opened`
    - updates `mail_recipients.last_event_at`
    - logs `mail_message.opened`
- `GET /t/c/{token}` validates the signature, redirects to the original URL and, on first valid hit:
    - sets `mail_messages.clicked_first_at`
    - backfills `mail_messages.opened_first_at` if still null
    - upgrades `mail_recipients.status` from `sent|delivered_if_known|opened` to `clicked`
    - updates `mail_recipients.last_event_at`
    - logs `mail_message.clicked`
- when tracking is disabled in `settings.deliverability`, no new tracking events are persisted:
    - open endpoint stays a transparent GIF no-op
    - click endpoint returns `404` when no tracked link metadata is available

Tracking URL generation rule:

- open and click URLs are built from the resolved public tracking base URL, never from the current request host and never from a local dev URL

## One-click unsubscribe flow used in V1

- bulk outbound messages expose:
    - `List-Unsubscribe: <https://.../u/{token}>`
    - `List-Unsubscribe-Post: List-Unsubscribe=One-Click`
- unsubscribe token = `{mail_recipient_id}.{signature}`
- endpoint accepts both `GET` and `POST` on `/u/{token}`
- the endpoint is deliberately CSRF-exempt to remain compatible with mailbox clients and one-click unsubscribe flows
- on first valid unsubscribe:
    - `mail_recipients.unsubscribe_at` is set
    - `mail_recipients.last_event_at` is updated
    - `mail_recipients.status` becomes `unsubscribed` unless the recipient is already in `hard_bounced`, `replied` or `auto_replied`
    - `contact_emails.opt_out_at` is set when a linked contact email exists
    - `contact_emails.opt_out_reason` defaults to `one_click_unsubscribe`
    - Laravel logs `mail_recipient.unsubscribed`

## IMAP sync flow used in V1

- polling command: `php artisan mailbox:poll`
- scheduled polling cadence: every 5 minutes
- folders synced in V1: `INBOX`, `SENT`
- queue used for sync jobs: `mail-sync`
- mailbox lock key: `mailbox-sync:{mailbox_account_id}:{folder}`
- resume cursor:
    - `mailbox_accounts.last_inbox_uid` for `INBOX`
    - `mailbox_accounts.last_sent_uid` for `SENT`
- strict idempotence:
    - dedupe first on `(mailbox_account_id, provider_folder, provider_uid)`
    - then on `mail_messages.message_id_header`
    - safe resume by advancing UID after each successfully ingested message

`SyncMailboxFolderJob` is the Laravel → mail-gateway boundary for IMAP sync.

## IMAP sync payload between Laravel and mail-gateway

`SyncMailboxFolderJob` resolves and sends a payload with:

- `mailbox_account_id`: required integer
- `folder`: required enum `INBOX|SENT`
- `from_uid`: required integer, cursor for safe resume
- `provider`: required string, always `ovh_mx_plan`
- `email`: required mailbox email
- `username`: required IMAP username
- `password`: required IMAP password
- `imap_host`: required string
- `imap_port`: required integer
- `imap_secure`: required boolean
- `idempotency_key`: nullable string

### Sync response expected from mail-gateway

- `success`: required boolean
- `driver`: required string
- `message`: required string
- `accepted_at`: nullable ISO-8601 string
- `folder`: required enum `INBOX|SENT`
- `from_uid`: nullable integer
- `highest_uid`: nullable integer
- `messages`: required array

### messages[]

- `uid`: required integer
- `message_id_header`: nullable string, Laravel synthesizes one if missing
- `in_reply_to_header`: nullable string
- `references_header`: nullable string or string array
- `aegis_tracking_id`: nullable UUID string
- `from_email`: required string
- `to_emails`: required string array
- `cc_emails`: nullable string array
- `bcc_emails`: nullable string array
- `subject`: nullable string
- `html_body`: nullable string
- `text_body`: nullable string
- `headers_json`: nullable object
- `received_at`: nullable ISO-8601 string
- `sent_at`: nullable ISO-8601 string
- `attachments`: nullable array

## Thread resolution order frozen in V1

Priority order used by `ThreadResolver`:

1. `In-Reply-To`
2. `References`
3. known `Message-ID` correlation
4. cautious heuristic on normalized subject + participant overlap + 30-day window
5. new thread when confidence is insufficient

Heuristic rules:

- subject normalization strips repeated `Re:`, `Fw:`, `Fwd:`
- participant overlap ignores the mailbox own address
- heuristic comparison considers `from`, `to`, `cc`, `bcc`
- heuristic match only applies within the same mailbox and a 30-day window

## Draft type field mapping

**IMPORTANT:** The `type` enum is mapped differently between storage and API:

| Layer                     | Value for single | Value for multiple |
| ------------------------- | ---------------- | ------------------ |
| DB (`mail_drafts.mode`)   | `single`         | `bulk`             |
| API read (serialized)     | `single`         | `multiple`         |
| API write (POST/PUT body) | `single`         | `bulk`             |

Frontend `MailComposer` maps `mode === 'multiple'` → sends `type: 'bulk'` to API.

## Preflight API response

`POST /api/drafts/{draft}/preflight` returns:

```json
{
    "ok": true,
    "provider": "smtp2go",
    "providerLabel": "SMTP2GO",
    "providerReady": true,
    "mailboxValid": true,
    "hasTextVersion": true,
    "hasRemoteImages": false,
    "estimatedWeightBytes": 12480,
    "recipientSummary": {
        "total": 100,
        "deliverable": 95,
        "excluded": 2,
        "optOut": 1,
        "invalid": 2
    },
    "deliverability": {
        "linkCount": 3,
        "remoteImageCount": 0,
        "attachmentCount": 0,
        "attachmentSizeBytes": 0,
        "htmlSizeBytes": 8192
    },
    "errors": [{ "code": "mailbox_invalid", "message": "..." }],
    "warnings": [{ "code": "remote_images_detected", "message": "..." }],
    "deliverableRecipients": [],
    "excludedRecipients": [],
    "optOutRecipients": [],
    "invalidRecipients": []
}
```

`errors[]` are blocking — `ok` is `false` when any exist.
`warnings[]` are advisory — `ok` can still be `true`.

Text-first rule now used by the backend:

- `text_body` is the primary content path in V1
- `html_body` stays optional
- preflight blocks scheduling when both `text_body` and `html_body` are empty
- when `html_body` is empty but `text_body` is present, outbound dispatch synthesizes a minimal HTML version from the text body
- when `text_body` is empty but `html_body` is present, outbound dispatch synthesizes a plain-text version from the HTML
- global signature stays split:
    - `global_signature_text` is appended to the outbound text body
    - `global_signature_html` is appended to the outbound HTML body

Preflight URL and MIME guards now enforced:

- preflight analyzes the final outbound body after signature append and text/HTML synthesis
- blocking error codes can include:
    - `link_requires_public_base`
    - `link_not_https`
    - `link_not_public`
    - `image_requires_public_base`
    - `image_not_https`
    - `image_not_public`
    - `tracking_base_url_invalid`
    - `bulk_unsubscribe_unavailable`
- `hasTextVersion` reflects the final outbound `text/plain` availability, including synthesized text when HTML-only content is authored

## Validation response shape used by JSON endpoints

When a JSON request fails validation, Laravel now returns:

```json
{
    "message": "Le champ ...",
    "errors": {
        "field_name": ["Le champ ..."]
    }
}
```

Rules:

- `message` is an aggregated user-facing sentence built from the actual field errors
- `errors` keeps field-level details for precise UI handling
- this applies both to FormRequest validation and to manual `ValidationException` raised by business services

## Query parameters

### Contacts

- `search`: nullable string
- `status`: nullable enum `all|active|bounced|unsubscribed`
- `score`: nullable enum `all|engaged|interested|warm|cold|excluded`

### Organizations

- `search`: nullable string

### Dashboard

- no query parameters in V1

### Settings

- no query parameters in V1

## CRM tables added in phase 2

### organizations

- `id`: required integer
- `name`: required string
- `domain`: nullable string
- `website`: nullable string
- `notes`: nullable string

### POST /api/organizations request

- `name`: required string
- `domain`: nullable string
- `website`: nullable string
- `notes`: nullable string

### POST /api/organizations response

- `message`: required string, French operator-facing success message
- `organization`: required object

#### organization

- `id`: required integer
- `name`: required string
- `domain`: nullable string
- `contactCount`: required integer
- `sentCount`: required integer
- `lastActivityAt`: nullable string

### GET /api/organizations/{organization} response

- `organization`: required object
- `organization.id`: required integer
- `organization.name`: required string
- `organization.domain`: nullable string
- `organization.website`: nullable string
- `organization.notes`: nullable string
- `organization.contactCount`: required integer
- `organization.sentCount`: required integer
- `organization.lastActivityAt`: nullable string
- `organization.contacts`: required array
- `organization.recentThreads`: required array

### PUT /api/organizations/{organization} request

- `name`: required string
- `domain`: nullable string
- `website`: nullable string
- `notes`: nullable string

### DELETE /api/organizations/{organization}

- rejects deletion while contacts are still attached
- keeps threads and recipients by detaching `organization_id` only when the organization can be deleted
- returns `{ "message": "Organisation supprimée." }` on success

### contacts

- `id`: required integer
- `organization_id`: required integer for manual create/update; import can preserve the existing linked organization on exact-email updates
- `first_name`: nullable string
- `last_name`: nullable string
- `full_name`: nullable string
- `job_title`: nullable string
- `phone`: nullable string
- `phone_landline`: nullable string
- `phone_mobile`: nullable string
- `linkedin_url`: nullable string
- `country`: nullable string
- `city`: nullable string
- `tags_json`: required array, possibly empty
- `notes`: nullable string
- `status`: nullable string

### POST /api/contacts request

- `organizationId`: required integer, must exist
- `firstName`: nullable string
- `lastName`: nullable string
- `fullName`: nullable string
- `title`: nullable string
- `email`: required string, unique on `contact_emails.email`
- `phone`: nullable string
- `phoneLandline`: nullable string
- `phoneMobile`: nullable string
- `linkedinUrl`: nullable `http|https` URL
- `notes`: nullable string
- `status`: nullable string

### POST /api/contacts response

- `message`: required string, French operator-facing success message
- `contact`: required object

#### contact

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

### GET /api/contacts/{contact} response

- `contact`: required object
- `contact.id`: required integer
- `contact.firstName`: required string
- `contact.lastName`: required string
- `contact.fullName`: nullable string
- `contact.primaryEmail`: nullable string
- `contact.title`: nullable string
- `contact.phone`: nullable string
- `contact.phoneLandline`: nullable string
- `contact.phoneMobile`: nullable string
- `contact.linkedinUrl`: nullable string
- `contact.country`: nullable string
- `contact.city`: nullable string
- `contact.tags`: required array
- `contact.notes`: nullable string
- `contact.status`: nullable string
- `contact.organizationId`: nullable integer
- `contact.organizationName`: nullable string
- `contact.emails`: required array
- `contact.recentThreads`: required array
- `contact.stats`: required object

### PUT /api/contacts/{contact} request

- `organizationId`: required integer, must exist
- `firstName`: nullable string
- `lastName`: nullable string
- `fullName`: nullable string
- `title`: nullable string
- `email`: required string, unique on `contact_emails.email` excluding the current primary email
- `phone`: nullable string
- `phoneLandline`: nullable string
- `phoneMobile`: nullable string
- `linkedinUrl`: nullable `http|https` URL
- `notes`: nullable string
- `status`: nullable string

### DELETE /api/contacts/{contact}

- removes the contact and its linked `contact_emails`
- keeps historical threads and recipients by detaching `contact_id` / `contact_email_id`
- returns `{ "message": "Contact supprimé." }`

### Import / Export module contract

The dedicated backend module is exposed on:

- `GET /api/import-export`
- `GET /api/import-export/template`
- `GET /api/import-export/export`
- `POST /api/import-export/preview`
- `POST /api/import-export/confirm`

Legacy contact-import routes stay available and point to the same implementation:

- `GET /api/contacts/imports/template`
- `GET /api/contacts/imports/export`
- `POST /api/contacts/imports/preview`
- `POST /api/contacts/imports`

`GET /api/import-export` response:

- `module.moduleKey`: required string, currently `contacts_organizations`
- `module.pagePath`: required string, currently `/contacts/imports`
- `module.previewEndpoint`: required string
- `module.confirmEndpoint`: required string
- `module.templateEndpoint`: required string
- `module.exportEndpoint`: required string
- `module.acceptedFileTypes`: required array (`csv`, `xlsx`)
- `module.templateColumns`: required array of template headers
- `module.acceptedAliases`: required object keyed by backend field name
- `module.recentImports`: required array

### Import preview contract

`POST /api/import-export/preview` and `POST /api/contacts/imports/preview` expect a multipart file upload:

- `file`: required file, `csv|txt|xlsx`

Response shape:

- `preview.moduleKey`: required string
- `preview.previewToken`: required string
- `preview.sourceName`: required string
- `preview.sourceType`: required enum `csv|txt|xlsx`
- `preview.templateColumns`: required array
- `preview.detectedColumns`: required array
- `preview.mapping`: required object
- `preview.sampleRows`: required array
- `preview.persistedFields`: required array
- `preview.summary`: required object
- `preview.counters`: required object
- `preview.errors`: required array
- `preview.warnings`: required array
- `preview.conflicts`: required array
- `preview.organizationSummary`: required object
- `preview.contactSummary`: required object
- `preview.rows`: required array

`preview.summary`:

- `totalRows`: required integer
- `validRows`: required integer
- `writeRows`: required integer
- `createRows`: required integer
- `updateRows`: required integer
- `unchangedRows`: required integer
- `skipRows`: required integer
- `errorRows`: required integer
- `invalidRows`: required integer
- `duplicateExistingRows`: required integer
- `duplicateFileRows`: required integer
- `organizationMatches`: required integer
- `organizationCreates`: required integer
- `organizationUpdates`: required integer
- `contactCreates`: required integer
- `contactUpdates`: required integer
- `contactUnchanged`: required integer

`preview.counters`:

- `create`: required integer
- `update`: required integer
- `unchanged`: required integer
- `skip`: required integer
- `error`: required integer

`preview.rows[]`:

- `lineNumber`: required integer
- `status`: required enum `valid|unchanged|invalid|duplicate_in_file`
- `action`: required enum `create|update|unchanged|skip|error`
- `reasonCode`: nullable string
- `reason`: nullable string
- `primaryEmail`: nullable string
- `name`: nullable string
- `organizationName`: nullable string
- `linkedinUrl`: nullable string
- `phoneLandline`: nullable string
- `phoneMobile`: nullable string
- `plannedActions.organization`: required object `{ code, label }`
- `plannedActions.contact`: required object `{ code, label }`
- `organization`: required object
- `organization.action`: required enum `create|update|reuse|keep_existing|preserve_existing|missing|ambiguous`
- `organization.writeAction`: required enum `create|update|unchanged`
- `organization.willWrite`: required boolean
- `organization.matchStrategy`: nullable enum `domain|name|existing`
- `organization.changes[]`: diff items `{ field, label, current, incoming }`
- `contact`: required object
- `contact.action`: required enum `create|update|unchanged`
- `contact.willWrite`: required boolean
- `contact.matchStrategy`: required enum `exact_email|exact_email_missing`
- `contact.changes[]`: diff items `{ field, label, current, incoming }`
- `normalized`: required object
- `persistedFields`: required array
- `errors`: required array
- `warnings`: required array
- `conflicts`: required array
- `existingContact`: nullable object

Business rules now enforced during preview/import/export:

- the dry-run never writes contacts or organizations
- the import/export module works on a combined `organization + contact` row model
- `primary_email` is the minimum reliable dedupe key for contacts
- exact email match previews as `action = update` or `action = unchanged`
- duplicate emails inside the same file preview as `action = skip`
- new contacts require a company; existing contacts may keep their linked organization when the CSV omits or conflicts on company
- organizations match first on exact domain, then on normalized exact name, otherwise Laravel creates the organization at confirmation time
- organization updates are conservative: Laravel only fills missing `domain` / `website` values and preserves conflicting existing values with warnings
- export and template share the same column order so the CSV is round-trippable
- confirmation is single-use per `previewToken`

### Import confirm contract

`POST /api/import-export/confirm` and `POST /api/contacts/imports` request:

- `previewToken`: required string returned by preview

Response shape:

- `moduleKey`: required string
- `message`: required string
- `batch`: required object
- `summary`: required object
- `rows`: required array

`batch`:

- `id`: required integer
- `moduleKey`: required string
- `sourceName`: required string
- `sourceType`: required string
- `status`: required string
- `importedContactsCount`: required integer
- `skippedRowsCount`: required integer
- `invalidRowsCount`: required integer
- `summary`: required object
- `processedAt`: nullable ISO-8601 string

`summary`:

- `moduleKey`: required string
- `totalRows`: required integer
- `importedRows`: required integer
- `createdRows`: required integer
- `updatedRows`: required integer
- `unchangedRows`: required integer
- `skippedRows`: required integer
- `errorRows`: required integer
- `invalidRows`: required integer
- `counters.create|update|unchanged|skip|error`: required integers

`rows[]`:

- `resultStatus`: required enum `imported|skipped|error`
- `resultAction`: required enum `create|update|unchanged|skip|error`
- `resultMessage`: required string
- preview row fields are echoed back for frontend display

### Template and export downloads

`GET /api/import-export/template`, `GET /api/contacts/imports/template`, `GET /api/import-export/export`, and `GET /api/contacts/imports/export` all use the same stable CSV header order:

- `societe`
- `prenom`
- `nom`
- `email`
- `linkedin`
- `telephone_fixe`
- `telephone_portable`

### POST /api/contacts/{contact}/emails request

- `email`: required string, unique on `contact_emails.email`
- `isPrimary`: nullable boolean

### DELETE /api/contacts/{contact}/emails/{contactEmail}

- rejects deletion when the email does not belong to the contact
- rejects deletion when it is the last remaining address
- promotes another address as primary when the deleted one was primary

### contact_emails

- `id`: required integer
- `contact_id`: required integer
- `email`: required string
- `is_primary`: required boolean
- `opt_out_at`: nullable datetime
- `opt_out_reason`: nullable string
- `bounce_status`: nullable string
- `last_seen_at`: nullable datetime

### mail_attachments

- `id`: required integer
- `message_id`: nullable integer
- `draft_id`: nullable integer
- `original_name`: required string
- `mime_type`: required string
- `size_bytes`: required integer
- `storage_disk`: required string
- `storage_path`: required string
- `content_id`: nullable string
- `disposition`: nullable string

## Enums

### Frozen mail statuses

- `draft`
- `scheduled`
- `queued`
- `sending`
- `sent`
- `delivered_if_known`
- `opened`
- `clicked`
- `replied`
- `auto_replied`
- `soft_bounced`
- `hard_bounced`
- `unsubscribed`
- `failed`
- `cancelled`

### Mail message classifications used by dashboard alerts

- `human_reply`
- `auto_reply`
- `out_of_office`
- `auto_ack`
- `soft_bounce`
- `hard_bounce`
- `system`
- `unknown`

Current inbound fallback rule:

- `human_reply` is kept for messages that clearly look like replies or that resolve to an existing thread after classifier fallback
- unmatched inbound messages with no auto-reply, bounce or system signal remain `unknown`

### Thread statuses currently persisted

- `active`
- `replied`
- `auto_reply`
- `hard_bounced`

### Contact scoreLevel

- `cold`
- `warm`
- `interested`
- `engaged`
- `excluded`

### Dashboard stats.healthStatus

- `good`
- `degraded`
- `critical`
- `unknown` (when mailbox health is null or not yet evaluated)

### Draft type exposed to frontend

- `single`
- `multiple`

### Draft mode stored in database

- `single`
- `bulk`

### Campaign statuses used in this phase

- `draft`
- `scheduled`
- `queued`
- `sending`
- `sent`
- `cancelled`
- `failed`

### Threading headers persisted on outbound messages

- `message_id_header`: required string
- `in_reply_to_header`: nullable string
- `references_header`: nullable string
- `aegis_tracking_id`: required UUID string

### Thread payload from `GET /api/threads`

- `id`: required integer
- `publicUuid`: required UUID string
- `subject`: required string
- `contactName`: nullable string
- `organization`: nullable string
- `replyReceived`: required boolean
- `autoReplyReceived`: required boolean
- `lastDirection`: required enum `in|out`
- `lastActivityAt`: nullable ISO-8601 string
- `messageCount`: required integer

### Thread payload from `GET /api/threads/{thread}`

- `thread`: required object
- `thread.id`: required integer
- `thread.publicUuid`: required UUID string
- `thread.subject`: required string
- `thread.contactName`: nullable string
- `thread.organization`: nullable string
- `thread.replyReceived`: required boolean
- `thread.autoReplyReceived`: required boolean
- `thread.lastDirection`: required enum `in|out`
- `thread.lastActivityAt`: nullable ISO-8601 string
- `thread.messages`: required array

### thread.messages[]

- `id`: required integer
- `direction`: required enum `in|out`
- `fromEmail`: required string
- `toEmails`: required string array
- `subject`: required string
- `classification`: required enum `human_reply|auto_reply|out_of_office|auto_ack|soft_bounce|hard_bounce|system|unknown`
- `messageIdHeader`: required string
- `inReplyToHeader`: nullable string
- `referencesHeader`: nullable string
- `sentAt`: nullable ISO-8601 string
- `receivedAt`: nullable ISO-8601 string
- `hasAttachments`: required boolean
- `attachmentCount`: required integer

## Activity timeline payload

`GET /activity` exposes:

- `events`: required array

### events[]

- `id`: required integer (or string `tracking-{id}` for tracking events)
- `threadId`: nullable integer
- `title`: required string
- `description`: nullable string
- `status`: required enum — for outbound messages, uses `mail_recipients.status` as source of truth (e.g. `queued|sending|sent|failed|opened|clicked|replied|cancelled`). For inbound messages, derived from classification (e.g. `replied|auto_replied|soft_bounced|hard_bounced|delivered_if_known`). For orphan outbound messages without a recipient, falls back to `sent` if `sent_at` is set, otherwise `queued`.
- `direction`: required enum `outbound|inbound`
- `isAutoReply`: required boolean
- `isBounce`: required boolean
- `date`: nullable ISO-8601 string

## Settings page payload

`GET /settings` exposes:

- `settings`: required object
- `settings.mail`: required object
- `settings.cadence`: required object
- `settings.scoring`: required object
- `settings.signature`: required object

Notes:

- `settings.mail` mirrors the mail settings snapshot used by `GET /api/settings`
- `settings.cadence` is derived from `settings.general`
- `settings.scoring` is derived from `settings.general`
- `settings.signature` mirrors the global signature stored in mail settings
- V1 no longer exposes a dedicated deliverability section in Settings
- SPF / DKIM / DMARC diagnostics are not exposed through the Settings page
- DNS authentication stays an external prerequisite managed outside the application

## Business rules frozen for page projections

### score

Computed from `mail_recipients.status` with current scoring settings:

- `opened` => `open_points`
- `clicked` => `click_points`
- `replied` => `reply_points`
- `auto_replied` => `auto_reply_points`
- `soft_bounced` => `soft_bounce_points`
- `hard_bounced` => `hard_bounce_points`
- `unsubscribed` => `unsubscribe_points`

### scoreLevel

- `excluded` if `excluded = true` or `unsubscribed = true`
- `engaged` if score `>= 8`
- `interested` if score `>= 4`
- `warm` if score `>= 1`
- `cold` otherwise

### excluded

`true` when at least one contact email or recipient is hard bounced.

### unsubscribed

`true` when at least one contact email has `opt_out_at` or at least one recipient is unsubscribed.

### lastActivityAt

Formatted string `YYYY-MM-DD HH:mm` in app timezone, or `null`.

Sources used:

- `contact_emails.last_seen_at`
- `mail_threads.last_message_at`
- `mail_recipients.last_event_at`
- `mail_recipients.sent_at`
- `mail_recipients.replied_at`
- `mail_recipients.auto_replied_at`
- `mail_recipients.bounced_at`
- `mail_recipients.unsubscribe_at`

### organization.contactCount

Count of `contacts` linked by `organization_id`.

### organization.sentCount

Count of recipients that reached send or post-send states.

### dashboard.scheduledSends

Source: `mail_drafts` with `status = scheduled` and non-null `scheduled_at`.

`recipientCount` rule:

- sum of linked campaign recipients when a campaign exists
- fallback to `1` for `mode = single`
- fallback to `0` otherwise

## Template API contract

### Template payload

- `id`: required integer
- `name`: required string
- `slug`: required string on single-resource responses
- `subject`: required string
- `htmlBody`: nullable string on single-resource responses
- `textBody`: nullable string on single-resource responses
- `active`: required boolean
- `usageCount`: required integer
- `createdAt`: nullable ISO-8601 string on single-resource responses
- `updatedAt`: nullable `YYYY-MM-DD HH:mm`

### Template request body

- `name`: required string
- `subject`: required string
- `htmlBody`: nullable string
- `textBody`: nullable string
- `active`: nullable boolean

### Template activation endpoints

- `POST /api/templates/{template}/archive` sets `active = false`
- `POST /api/templates/{template}/activate` sets `active = true`

## Draft API contract

### Draft payload

- `id`: required integer
- `templateId`: nullable integer
- `type`: required enum `single|multiple`
- `outboundProvider`: required enum `ovh_mx_plan|smtp2go`
- `outboundProviderLabel`: required string
- `subject`: required string
- `htmlBody`: nullable string
- `textBody`: nullable string
- `signatureHtml`: nullable string
- `status`: required string
- `scheduledAt`: nullable `YYYY-MM-DD HH:mm`
- `recipientCount`: required integer
- `recipients`: required array
- `attachmentCount`: required integer on single-resource responses
- `updatedAt`: nullable `YYYY-MM-DD HH:mm`

### Draft recipients shape

- `email`: nullable string
- `contactId`: nullable integer
- `contactEmailId`: nullable integer
- `organizationId`: nullable integer
- `name`: nullable string

### Draft request body

- `type`: required enum `single|bulk`
- `templateId`: nullable integer
- `subject`: required string
- `htmlBody`: nullable string
- `textBody`: nullable string
- `signatureHtml`: nullable string
- `recipients`: nullable array of recipient objects

### Draft schedule request body

- `scheduledAt`: required ISO or parseable datetime string
- `name`: nullable string

### Draft deletion behavior

- `DELETE /api/drafts/{draft}` deletes the draft and its technical campaign artifacts only when no sent or post-send history exists
- `DELETE /api/drafts` accepts `{ "ids": [1, 2, ...] }`
- bulk deletion returns:
    - `message`: required string
    - `deletedCount`: required integer

## Campaign API contract

### Campaign payload

- `id`: required integer
- `draftId`: nullable integer
- `name`: required string
- `status`: required string
- `outboundProvider`: required enum `ovh_mx_plan|smtp2go`
- `outboundProviderLabel`: required string
- `deletedAt`: nullable ISO-8601 string
- `type`: required enum `single|multiple`
- `recipientCount`: required integer
- `progressPercent`: required integer
- `openCount`: required integer
- `replyCount`: required integer
- `bounceCount`: required integer
- `scheduledAt`: nullable `YYYY-MM-DD HH:mm`
- `createdAt`: nullable ISO-8601 string
- `updatedAt`: nullable `YYYY-MM-DD HH:mm`
- `lastEditedAt`: nullable ISO-8601 string

### Campaign autosave request

`POST /api/campaigns/autosave` request:

- `campaignId`: nullable integer
- `draftId`: nullable integer
- `expectedUpdatedAt`: nullable ISO-8601 string used for simple stale-write rejection
- `name`: nullable string
- `type`: required enum `single|bulk`
- `templateId`: nullable integer
- `subject`: required string
- `htmlBody`: nullable string
- `textBody`: nullable string
- `signatureHtml`: nullable string
- `recipients`: nullable array

Autosave behavior:

- Laravel keeps the technical `mail_drafts` layer
- autosave creates or updates the linked `mail_campaigns` row on every significant change
- unsent and unscheduled campaigns remain in business status `draft`
- stale autosave writes return HTTP `409`

### Campaign audiences payload

`GET /api/campaigns/audiences` returns:

- `contacts`: required array of selectable contacts
- `organizations`: required array of selectable organizations with nested contacts
- `recentImports`: required array of recent contact import batches with nested contacts

### Campaign detail payload used by `Campaigns/Show`

- `draft`: nullable object, serialized with the same shape as `GET /api/drafts/{draft}`
- `recipients`: required array

### campaign.recipients[]

- `id`: required integer
- `email`: required string
- `status`: required string
- `contactName`: nullable string
- `organization`: nullable string (from `contact.organization.name`)
- `scheduledFor`: nullable `YYYY-MM-DD HH:mm`
- `lastEventAt`: nullable `YYYY-MM-DD HH:mm`
- `lastSentAt`: nullable ISO 8601 string — date of the most recent sent message for this recipient
- `lastSentSubject`: nullable string — subject of the most recent sent message for this recipient

### Campaign deletion behavior

- `DELETE /api/campaigns/{campaign}` hard-deletes the campaign and its linked technical draft only when the campaign has no queued/sent/message activity
- when the campaign already has dispatch activity, Laravel performs a logical deletion instead:
    - `mail_campaigns.deleted_at` is filled
    - campaign business `status` becomes `cancelled`
    - linked draft status becomes `cancelled` and `scheduled_at` is cleared
    - recipients still in `draft|scheduled|queued` become `cancelled`
    - `mail_events`, `mail_messages`, `mail_threads`, sent recipients, and timeline history are preserved
- standard campaign listings exclude logically deleted campaigns by default
- `GET /api/campaigns?includeDeleted=1` includes logically deleted campaigns and exposes their `deletedAt`
- `GET /campaigns/{campaign}` still resolves a logically deleted campaign so its history remains consultable

## Outbound dispatch payload between Laravel and mail-gateway

`DispatchMailMessageJob` resolves and sends a payload with:

- `mailbox_account_id`: required integer
- `mail_message_id`: required integer
- `thread_id`: required integer
- `campaign_id`: required integer
- `recipient_id`: required integer
- `provider`: required enum `ovh_mx_plan|smtp2go`
- `email`: required sender email
- `username`: required SMTP username
- `password`: required SMTP password
- `smtp_host`: required string
- `smtp_port`: required integer
- `smtp_secure`: required boolean
- `from_email`: required string
- `from_name`: required string
- `to_emails`: required string array
- `subject`: required string
- `html_body`: nullable string
- `text_body`: nullable string
- `message_id_header`: required string
- `in_reply_to_header`: nullable string
- `references_header`: nullable string
- `aegis_tracking_id`: required UUID string
- `headers_json`: required object
- `attachments`: required array, possibly empty

### Dispatch response consumed from mail-gateway

- `success`: required boolean
- `driver`: required string
- `message`: required string
- `accepted_at`: nullable ISO-8601 string
- `message_id_header`: nullable string
- `headers_json`: nullable object

Laravel persistence rules on dispatch result:

- outbound `mail_messages.message_id_header` is updated from gateway response when provided
- outbound `mail_messages.headers_json` keeps the locally generated threading headers and merges gateway-returned headers
- the raw gateway result is stored under `headers_json.gateway` on success
- the raw gateway result is stored under `headers_json.gateway_error` on failure
- `mail_events` logs `mail_message.queued`, `mail_message.sending`, `mail_message.dispatch_requested`, then `mail_message.sent` or `mail_message.failed`
- if a delayed job wakes up after a draft was unscheduled or a recipient/campaign is no longer dispatchable, Laravel skips the send and logs `mail_message.dispatch_skipped`

## Settings actually used by outbound scheduling and dispatch

Actual persisted keys in the current repo:

- `settings.mail`
- `settings.general`
- `settings.deliverability`

There is currently no separate persisted `settings.throttling` key. Cadence and auto-stop settings live under `settings.general` in this phase.

### settings.mail

- `mailbox_provider`
- `active_provider`
- `sender_email`
- `sender_name`
- `global_signature_html`
- `global_signature_text`
- `clear_signature` optional on `PUT /api/settings/mail` only
- `mailbox_username`
- `mailbox_password` optional on `PUT /api/settings/mail` only
- `imap_host`
- `imap_port`
- `imap_secure`
- `sync_enabled`
- `send_enabled`
- `send_window_start`
- `send_window_end`
- `providers.ovh_mx_plan.smtp_host`
- `providers.ovh_mx_plan.smtp_port`
- `providers.ovh_mx_plan.smtp_secure`
- `providers.smtp2go.smtp_host`
- `providers.smtp2go.smtp_port`
- `providers.smtp2go.smtp_secure`
- `providers.smtp2go.smtp_username`
- `providers.smtp2go.smtp_password` optional on `PUT /api/settings/mail` only
- `providers.smtp2go.send_enabled`

### settings.general

- `daily_limit_default`
- `hourly_limit_default`
- `min_delay_seconds`
- `jitter_min_seconds`
- `jitter_max_seconds`
- `slow_mode_enabled`
- `stop_on_consecutive_failures`
- `stop_on_hard_bounce_threshold`

Behavior:

- `daily_limit_default`, `hourly_limit_default`, `min_delay_seconds`, `jitter_*`, `slow_mode_enabled` drive progressive slot planning before queue placement
- `stop_on_consecutive_failures` and `stop_on_hard_bounce_threshold` drive the simple auto-stop check before each dispatch

Mail settings update rule:

- `PUT /api/settings/mail` preserves the existing global signature when `global_signature_html` / `global_signature_text` are sent as `null`
- explicit signature clearing now requires `clear_signature = true`
- `active_provider` is persisted in `settings.mail` and is the only source of truth for new drafts
- `PUT /api/settings/mail` cannot activate a provider whose SMTP configuration is incomplete or disabled
- `GET /settings` and `GET /api/settings` must stay non-fatal if `smtp_provider_accounts` is not migrated yet locally; in that case `providers.smtp2go` stays visible with `configured = false`, `activatable = false`, `ready = false`, `health_status = warning`, and a health message instructing the operator to run `php artisan migrate`
- `PUT /api/settings/mail` must return `422` instead of `500` when an operator tries to configure or activate SMTP2GO before the `smtp_provider_accounts` table exists
- `POST /api/settings/mail/test-smtp` and `POST /api/settings/mail/test-imap` require an explicit `provider`
- `POST /api/settings/mail/test-smtp` and `POST /api/settings/mail/test-imap` can run directly from unsaved form overrides if the payload already contains all required connection fields
- `POST /api/settings/mail/test-smtp` never falls back from `smtp2go` to OVH credentials
- those test endpoints return French operator-facing messages for success and the main failure families: missing field, invalid credentials, timeout, refused connection, TLS/SSL mismatch, likely host/port error, and generic failure
- raw technical details remain in logs and `mail_events`, not in the user-facing `message`

### settings.deliverability used by preflight

- `tracking_opens_enabled`
- `tracking_clicks_enabled`
- `max_links_warning_threshold`
- `max_remote_images_warning_threshold`
- `html_size_warning_kb`
- `attachment_size_warning_mb`
- `public_base_url`
- `tracking_base_url`

V1 note:

- these values remain internal runtime settings used by tracking, unsubscribe, and preflight logic
- SPF / DKIM / DMARC checks are not persisted or exposed as an operator-facing contract anymore

Effective base URL behavior:

- `public_base_url` is the explicit public HTTPS base for links, images and relative URL normalization
- `tracking_base_url` is the explicit public HTTPS base for open/click/unsubscribe URLs
- if `tracking_base_url` is empty, Laravel falls back to the resolved `public_base_url`
- if `public_base_url` is empty, Laravel falls back to `APP_URL`
- config defaults can be seeded from `MAIL_PUBLIC_BASE_URL` and `MAIL_TRACKING_BASE_URL`
- preflight blocks tracking-dependent or bulk sends when the resolved base is missing, local, private or non-HTTPS

## Inbound business rules frozen in this phase

- `auto_reply`, `out_of_office` and `auto_ack` remain visible but never count as human reply
- inbound messages skipped because a mailbox lock is active log `mailbox.sync_skipped_locked`
- UID cursor advances only after each successfully ingested message, so retry resumes safely after partial failure
- `human_reply` sets `mail_threads.reply_received = true`
- `auto_reply`, `out_of_office`, `auto_ack` set `mail_threads.auto_reply_received = true`
- `hard_bounce` remains distinct from `soft_bounce`
- `hard_bounce` updates `contact_emails.bounce_status = hard_bounced`
- `human_reply` marks the matched recipient `replied`
- `auto_reply`, `out_of_office`, `auto_ack` mark the matched recipient `auto_replied`
- `soft_bounce` marks the matched recipient `soft_bounced`
- `hard_bounce` marks the matched recipient `hard_bounced`
- `human_reply` and `hard_bounce` cancel queued follow-ups for the same campaign/email when present

## Auto-stop behavior used in this phase

- before each outbound dispatch, Laravel checks the current campaign
- if `failed` recipients count reaches `stop_on_consecutive_failures`, the next queued recipient is marked `cancelled`
- if `hard_bounced` recipients count reaches `stop_on_hard_bounce_threshold`, the next queued recipient is marked `cancelled`
- Laravel logs `mail_campaign.auto_stopped`
- campaign status becomes `failed`

## Preflight contract

### Response shape

- `ok`: required boolean
- `provider`: required enum `ovh_mx_plan|smtp2go`
- `providerLabel`: required string
- `providerReady`: required boolean
- `mailboxValid`: required boolean
- `hasTextVersion`: required boolean
- `hasRemoteImages`: required boolean
- `estimatedWeightBytes`: required integer
- `recipientSummary`: required object
- `deliverability`: required object
- `errors`: required array
- `warnings`: required array
- `deliverableRecipients`: required array
- `excludedRecipients`: required array
- `optOutRecipients`: required array
- `invalidRecipients`: required array

### recipientSummary

- `total`: required integer
- `deliverable`: required integer
- `excluded`: required integer
  hard-bounced recipients only
- `optOut`: required integer
- `invalid`: required integer

### deliverability

- `linkCount`: required integer
- `remoteImageCount`: required integer
- `attachmentCount`: required integer
- `attachmentSizeBytes`: required integer
- `htmlSizeBytes`: required integer

### errors[] and warnings[]

- `code`: required string
- `message`: required string

### Recipient detail arrays

- `deliverableRecipients[]`: array of recipient detail objects
- `excludedRecipients[]`: array of recipient detail objects with `reason = hard_bounced`
- `optOutRecipients[]`: array of recipient detail objects with `reason = opt_out`
- `invalidRecipients[]`: array of recipient detail objects with `reason = invalid_email`

### Recipient detail object

- `email`: nullable string
- `contactId`: nullable integer
- `contactEmailId`: nullable integer
- `organizationId`: nullable integer
- `name`: nullable string
- `reason`: nullable string on `deliverableRecipients[]`, required on exclusion arrays

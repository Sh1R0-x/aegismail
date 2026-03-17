# BACKEND CONTRACTS

## Scope

This document freezes the backend contracts exposed by Laravel to the current frontend pages in V1.

Rules kept:

- one mailbox only
- `provider = ovh_mx_plan`
- no Gmail logic
- no Google APIs
- no multi-provider abstraction
- one sending queue for all outgoing mail

## Inertia routes

- `GET /dashboard` -> `Dashboard`
- `GET /mails` -> `Mails/Index` — served by `MailsController`, payload from `ComposerPageDataService::mails()`
- `GET /contacts` -> `Contacts/Index`
- `GET /organizations` -> `Organizations/Index`
- `GET /drafts` -> `Drafts/Index` — payload includes both `drafts[]` and `templates[]`
- `GET /templates` -> `Templates/Index`
- `GET /campaigns` -> `Campaigns/Index`
- `GET /activity` -> `Activity/Index`
- `GET /settings` -> `Settings/Index`
- `GET /users` -> `Users/Index` — route present in V1, but no dedicated backend payload yet; the current page relies on its local default `users = []`

## API routes

### Templates

- `GET /api/templates`
- `POST /api/templates`
- `PUT /api/templates/{template}`
- `POST /api/templates/{template}/duplicate`
- `POST /api/templates/{template}/archive`
- `POST /api/templates/{template}/activate`

### Drafts

- `GET /api/drafts`
- `GET /api/drafts/{draft}`
- `POST /api/drafts`
- `PUT /api/drafts/{draft}`
- `POST /api/drafts/{draft}/duplicate`
- `POST /api/drafts/{draft}/preflight`
- `POST /api/drafts/{draft}/schedule`
- `POST /api/drafts/{draft}/unschedule`
- `POST /api/drafts/{draft}/campaign`

### Campaigns

- `GET /api/campaigns`

### Threads

- `GET /api/threads`
- `GET /api/threads/{thread}`

## Outbound flow used in V1

`POST /api/drafts/{draft}/schedule` now performs:
- draft validation
- preflight
- campaign creation or update
- deliverable `mail_recipients` creation
- per-recipient `mail_threads` creation
- per-recipient `mail_messages` creation before SMTP dispatch
- queue placement on the unique queue `mail-outbound`
- delayed dispatch according to cadence settings
- persistence of `sent` or `failed` outcomes in `mail_messages`, `mail_recipients`, `mail_events`

`DispatchMailMessageJob` is the Laravel → mail-gateway boundary for outbound sends.

Important runtime rule:
- campaign state is based on the first effective scheduled slot after send-window and ceiling adjustments, not only on the raw `scheduledAt` request value

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
- global signature stays split:
  - `global_signature_text` is appended to the outbound text body
  - `global_signature_html` is appended to the outbound HTML body

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

### contacts

- `id`: required integer
- `organization_id`: nullable integer
- `first_name`: nullable string
- `last_name`: nullable string
- `full_name`: nullable string
- `job_title`: nullable string
- `phone`: nullable string
- `notes`: nullable string
- `status`: nullable string

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

- `id`: required integer
- `title`: required string
- `description`: nullable string
- `status`: required enum `sent|replied|auto_replied|soft_bounced|hard_bounced|delivered_if_known`
- `direction`: required enum `outbound|inbound`
- `isAutoReply`: required boolean
- `isBounce`: required boolean
- `date`: nullable ISO-8601 string

## Settings page payload

`GET /settings` exposes:

- `settings`: required object
- `settings.mail`: required object
- `settings.deliverability`: required object
- `settings.cadence`: required object
- `settings.scoring`: required object
- `settings.signature`: required object

Notes:

- `settings.mail` mirrors the mail settings snapshot used by `GET /api/settings`
- `settings.cadence` is derived from `settings.general`
- `settings.scoring` is derived from `settings.general`
- `settings.signature` mirrors the global signature stored in mail settings

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

## Campaign API contract

### Campaign payload

- `id`: required integer
- `draftId`: nullable integer
- `name`: required string
- `status`: required string
- `type`: required enum `single|multiple`
- `recipientCount`: required integer
- `progressPercent`: required integer
- `openCount`: required integer
- `replyCount`: required integer
- `bounceCount`: required integer
- `scheduledAt`: nullable `YYYY-MM-DD HH:mm`
- `createdAt`: nullable ISO-8601 string
- `updatedAt`: nullable `YYYY-MM-DD HH:mm`

## Outbound dispatch payload between Laravel and mail-gateway

`DispatchMailMessageJob` resolves and sends a payload with:
- `mailbox_account_id`: required integer
- `mail_message_id`: required integer
- `thread_id`: required integer
- `campaign_id`: required integer
- `recipient_id`: required integer
- `provider`: required string, always `ovh_mx_plan`
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

- `sender_email`
- `sender_name`
- `global_signature_html`
- `global_signature_text`
- `clear_signature` optional on `PUT /api/settings/mail` only
- `mailbox_username`
- `imap_host`
- `imap_port`
- `imap_secure`
- `smtp_host`
- `smtp_port`
- `smtp_secure`
- `sync_enabled`
- `send_enabled`
- `send_window_start`
- `send_window_end`

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
- `POST /api/settings/mail/test-smtp` and `POST /api/settings/mail/test-imap` can run directly from unsaved form overrides if the payload already contains all required connection fields

### settings.deliverability used by preflight

- `tracking_opens_enabled`
- `tracking_clicks_enabled`
- `max_links_warning_threshold`
- `max_remote_images_warning_threshold`
- `html_size_warning_kb`
- `attachment_size_warning_mb`

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

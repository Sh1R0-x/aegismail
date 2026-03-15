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
- `GET /contacts` -> `Contacts/Index`
- `GET /organizations` -> `Organizations/Index`
- `GET /drafts` -> `Drafts/Index`
- `GET /templates` -> `Templates/Index`
- `GET /campaigns` -> `Campaigns/Index`

## API routes

### Templates
- `GET /api/templates`
- `POST /api/templates`
- `PUT /api/templates/{template}`
- `POST /api/templates/{template}/duplicate`
- `POST /api/templates/{template}/archive`

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

## Query parameters

### Contacts
- `search`: nullable string
- `status`: nullable enum `all|active|bounced|unsubscribed`
- `score`: nullable enum `all|engaged|interested|warm|cold|excluded`

### Organizations
- `search`: nullable string

### Dashboard
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
- `completed`
- `cancelled`
- `failed`

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

### recipientSummary
- `total`: required integer
- `deliverable`: required integer
- `excluded`: required integer
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

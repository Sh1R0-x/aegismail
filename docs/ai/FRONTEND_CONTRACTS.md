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

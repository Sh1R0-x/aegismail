# AEGIS MAILING — MASTER REFERENCE V1

## Purpose

This file is the project source of truth for AEGIS MAILING V1.

The product is a mailing and outreach tracking tool centered on:

- sending one-to-one and one-to-many emails
- reliable tracking of email exchanges
- contacts and organizations
- full event history
- deliverability
- future AI readiness

This project does **not** reuse Gmail logic from OppoLES.
The V1 target keeps **one OVH MX Plan mailbox** for identity and IMAP sync, with outbound SMTP limited to **OVH MX Plan** and **SMTP2GO**.

## Frozen V1 scope

### Mail provider

- one OVH mailbox only for identity and inbound sync
- IMAP for inbound sync on OVH only
- outbound SMTP limited to `ovh_mx_plan` and `smtp2go`
- `settings.mail.active_provider` is the single source of truth for new drafts
- each draft/campaign freezes its `outbound_provider` at creation time
- no Gmail
- no Google APIs
- no generic provider abstraction beyond OVH MX Plan + SMTP2GO

### Product shape

- CRM-style application
- left sidebar
- top header
- admin settings inside the app
- minimal user management in V1

### Main user flows

- simple mail
- multiple mail
- drafts
- reusable templates
- campaigns
- activity / timeline
- contacts
- organizations
- import / export contacts-organizations
- settings

## Architecture

### Backend / app

- Laravel for business app, auth, settings, contacts, organizations, campaigns, timeline, scoring, internal API
- SQLite for local development, PostgreSQL recommended for production
- database queue driver for local development, Redis recommended for production (queues, locks, send cadence)
- Local DB reset: `powershell -ExecutionPolicy Bypass -File .\scripts\reset-db.ps1` (see `docs/LOCAL_DEV_START.md`)
- Three distinct local databases: `database.sqlite` (dev app), `:memory:` (PHPUnit), `e2e.sqlite` (Playwright)

### Mail engine

- Node.js + TypeScript
- IMAP sync
- SMTP send
- MIME parsing
- threading
- bounce parsing
- auto-reply detection
- click/open tracking
- progressive send cadence

### Architecture rule

Laravel does not own low-level email complexity alone.
The mail engine does not own product UI or CRM logic.

## Core product principles

1. Strong traceability
2. Robust threading
3. Unified timeline
4. Deliverability first
5. Admin-editable settings
6. AI-ready data model without AI dependency in V1

## Message identity

Every outbound or inbound message must preserve:

- Message-ID
- aegis_tracking_id
- IMAP UID / folder technical pointers
- internal mail_thread_id

### Outbound rule

- generate aegis_tracking_id
- generate or control Message-ID
- store before SMTP send
- preserve reply headers when replying

### Inbound matching priority

1. In-Reply-To
2. References
3. known Message-ID correlation
4. cautious heuristic on normalized subject + participants + time window
5. create a new thread if confidence is too low

## Mail sync

### V1 folders

- INBOX
- SENT

### Sync rules

- scheduled sync every 1 to 5 minutes
- idempotent processing
- mailbox lock
- error-safe resume
- timestamped technical logs

### Distinguish these cases

- human reply
- auto-reply / out of office
- automatic acknowledgement
- soft bounce
- hard bounce
- technical failure

Auto-replies must never be treated as human replies.

## Deliverability and email quality

### Domain expectations

- valid SPF
- valid DKIM
- published DMARC
- coherent From identity
- bounce/error monitoring

DNS authentication remains a prerequisite managed outside the application.
V1 does not expose embedded SPF / DKIM / DMARC diagnostics or manual DNS retesting in Settings.

### Message construction

Every outbound email must include:

- plain-text version
- HTML version designed for email clients
- correct headers
- stable Message-ID
- safe links
- conservative and robust structure

### Rendering compatibility targets

- Gmail web
- Outlook Windows
- Outlook web
- Apple Mail
- iPhone Mail
- Gmail mobile

### Images

- secondary only
- message must remain understandable without images
- alt text required
- limited weight
- HTTPS only

## Progressive sending

This is the core of the product.

### Rules

- one queue for all outgoing messages
- never blast all at once
- configurable throughput
- configurable daily ceiling
- configurable hourly ceiling
- configurable minimum delay
- optional slow mode
- automatic stop on abnormal errors

### Preflight

Before any send or scheduling launch, the UI must show:

- valid mail configuration
- usable recipients
- exclusions / opt-outs
- invalid recipients
- estimated weight
- plain-text presence
- acceptable HTML structure
- remote images warnings
- deliverability warnings

## Validated business decisions

- one OVH mailbox in V1
- outbound SMTP providers limited to OVH MX Plan and SMTP2GO
- simple and multiple mail inside the same product
- same sending queue for all mails
- attachments supported in V1
- one global signature
- editable draft before scheduling
- reusable templates in V1
- simple scoring without AI in V1
- auto-reply handling in V1
- import / export is a first-class backend module in V1 for contacts + organizations with preview-first confirmation and mirrored CSV export
- default target: around 100 emails/day
- daily ceiling editable in settings
- absolute priority: deliverability + reliable exchange tracking

## Simple scoring V1

Signals:

- sent
- delivered_if_known
- opened
- clicked
- replied
- auto_replied
- soft_bounced
- hard_bounced
- unsubscribed
- time since last interaction

Heat labels:

- cold
- warm
- interested
- engaged
- exclude

## Main navigation

- Tableau de bord (Dashboard)
- Mails (unified hub: Envoyés, Brouillons, Programmés)
- Contacts
- Organizations
- Templates
- Campaigns
- Activity
- Import / Export
- Settings
- Users

Drafts are no longer a separate navigation item — they live under `/mails?tab=drafts`.

## Admin settings sections

### Mail

- sender address
- sender name
- global signature
- active outbound provider
- OVH mailbox + IMAP config
- OVH SMTP config
- SMTP2GO SMTP config
- sync state

### Cadence

- daily ceiling
- hourly ceiling
- minimum delay
- slow mode
- auto-stop rules

### Scoring

- score points
- heat levels

### Product

- users / roles
- templates
- status categories

## Frozen statuses

- draft
- scheduled
- queued
- sending
- sent
- delivered_if_known
- opened
- clicked
- replied
- auto_replied
- soft_bounced
- hard_bounced
- unsubscribed
- failed
- cancelled

## AI role split

### Claude

Owns:

- UI
- UX
- Vue components
- layouts
- Inertia pages
- visual consistency
- frontend docs
- frontend prop contract proposals

Must not:

- redesign backend architecture
- own final DB schema decisions
- implement SMTP/IMAP logic
- move outside validated UX scope

### Codex

Owns:

- Laravel backend
- migrations
- models
- services
- controllers
- routes
- policies
- jobs
- backend tests
- data contracts for frontend
- integration with mail engine

Must not:

- redefine UI/UX or design direction
- improvise a different product structure
- rewrite frontend architecture without explicit need

> Legacy location. The canonical project reference lives in `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md`.

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
The V1 target is **OVH MX Plan only**.

## Frozen V1 scope

### Mail provider
- one mailbox only
- OVH MX Plan only
- IMAP for inbound sync
- SMTP for outbound send
- no Gmail
- no Google APIs
- no multi-provider support in V1

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
- settings

## Architecture

### Backend / app
- Laravel for business app, auth, settings, contacts, organizations, campaigns, timeline, scoring, internal API
- PostgreSQL for persistent business data
- Redis for queues, scheduling, locks, send cadence

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
- one mailbox in V1
- OVH MX Plan only
- simple and multiple mail inside the same product
- same sending queue for all mails
- attachments supported in V1
- one global signature
- editable draft before scheduling
- reusable templates in V1
- simple scoring without AI in V1
- auto-reply handling in V1
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
- Dashboard
- Mails
- Contacts
- Organizations
- Drafts
- Templates
- Campaigns
- Activity
- Settings
- Users

## Admin settings sections

### Mail
- sender address
- sender name
- global signature
- IMAP/SMTP config
- sync state

### Deliverability
- SPF / DKIM / DMARC checks
- alert thresholds
- tracking toggles

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

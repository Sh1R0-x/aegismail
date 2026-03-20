# BACKEND_SCOPE.md

## Current backend targets

All V1 backend features are implemented and tested. The goal is now stability, correctness, and documentation alignment — no new feature development.

## Implemented backend features

- **CRM core**: contacts, organizations, contact_emails with CRUD, search, filters, split phones (phone_landline, phone_mobile), LinkedIn
- **Mail composition**: drafts (create, update, duplicate, delete, bulk-delete), templates (CRUD, duplicate, permanent delete), MailComposer single/multiple modes
- **Campaigns**: draft-first creation, autosave, preflight, scheduling, unscheduling, immediate send, clone, audience picker
- **Outbound mail**: real OVH MX Plan SMTP dispatch through Node mail-gateway, multipart body synthesis, public URL validation, send window, cadence, one queue (`mail-outbound`)
- **IMAP sync**: inbox + sent folder sync through Node mail-gateway, UID cursor resume, mailbox+folder lock, thread resolution, message classification (human_reply, auto_reply, out_of_office, auto_ack, soft_bounce, hard_bounce, system)
- **Tracking**: open pixel, click redirect, one-click unsubscribe (List-Unsubscribe headers), event persistence
- **Import/Export**: preview-first CSV/XLSX import, confirm with single-use preview token, template download, full export, round-trip support, French aliases, organization resolution
- **Settings**: general, mail (SMTP/IMAP test), deliverability (public URLs, tracking URLs), signature
- **Dashboard data**: sentToday, dailyLimit, healthStatus, bounceRate, activeCampaigns, scheduledCount, recentReplies, recentAlerts, scheduledSends — all from real DB queries
- **Activity**: global event timeline from persisted mail_messages
- **Attachments**: draft file attachments with MIME validation, max 10 MB

## Must maintain now

1. Keep one OVH MX Plan mailbox only
2. Keep one sending queue for all outgoing messages
3. Expose stable Inertia payloads for all pages
4. Persist and query all CRM entities
5. Compute score, scoreLevel, excluded, unsubscribed, lastActivityAt correctly
6. Deliver real SMTP send + IMAP sync through the mail-gateway
7. Persist tracking events and expose them to existing backend projections
8. Maintain stable Import / Export module contracts
9. Document exact backend/frontend contracts in `docs/ai`
10. Keep all tests passing (100 tests, 1370+ assertions)

## Must not do now

- build full Gmail-style integrations
- add Google APIs or Socialite Google
- add speculative provider abstractions
- move UI concerns into Laravel
- expose or maintain embedded SPF / DKIM / DMARC diagnostic flows in V1
- invent provider-specific behavior outside OVH MX Plan needs

## Frozen rules to keep

- frozen mail statuses stay unchanged
- frontend navigation stays unchanged
- Laravel owns business truth
- Node/TypeScript mail-gateway stays the low-level mail boundary
- contract changes must be documented under `docs/ai`

## Local database management

- Local dev uses SQLite: `app/database/database.sqlite`
- PHPUnit tests use `:memory:` (configured in `phpunit.xml`)
- E2E tests use `app/database/e2e.sqlite` (managed by `scripts/e2e-serve.ps1`)
- Reset local DB: `powershell -ExecutionPolicy Bypass -File .\scripts\reset-db.ps1`
- `scripts/dev.ps1` auto-detects pending migrations after startup and warns

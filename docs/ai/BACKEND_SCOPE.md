# BACKEND_SCOPE.md

## Current backend targets

The immediate goal is to support the existing frontend foundation with stable business data contracts while staying inside the frozen V1 scope.

## Current phase

This phase wires CRM payloads for the existing frontend pages and closes the last backend blockers for real commercial usage:
- `/dashboard`
- `/contacts`
- `/organizations`
- real OVH MX Plan SMTP dispatch through the Node mail-gateway
- real IMAP sync through the Node mail-gateway
- open/click tracking endpoints with persisted events

It also aligns the minimum missing backend entities:
- `organizations`
- `contacts`
- `contact_emails`
- `mail_attachments`

It now also includes a dedicated Import / Export backend module for contacts + organizations:
- preview-first CSV import
- explicit confirm step
- mirrored CSV export for round-trip edits
- conservative matching and diff exposure for the frontend

## Must implement now
1. Keep one OVH MX Plan mailbox only
2. Keep one sending queue for all outgoing messages
3. Expose stable Inertia payloads for Dashboard, Contacts, Organizations
4. Persist and query the CRM entities needed by the frontend
5. Compute simple score, scoreLevel, excluded, unsubscribed and lastActivityAt
6. Compute scheduled sends, contactCount and sentCount
7. Deliver real SMTP send + IMAP sync through the mail-gateway
8. Persist open/click tracking and expose it to the existing backend projections
9. Deliver a stable Import / Export module contract for contacts and organizations
10. Document exact backend/frontend contracts in `docs/ai`

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

# BACKEND_SCOPE.md

## Current backend targets

The immediate goal is to support the existing frontend foundation with stable business data contracts while staying inside the frozen V1 scope.

## Current phase

This phase wires CRM payloads for the existing frontend pages:
- `/dashboard`
- `/contacts`
- `/organizations`

It also aligns the minimum missing backend entities:
- `organizations`
- `contacts`
- `contact_emails`
- `mail_attachments`

## Must implement now
1. Keep one OVH MX Plan mailbox only
2. Keep one sending queue for all outgoing messages
3. Expose stable Inertia payloads for Dashboard, Contacts, Organizations
4. Persist and query the CRM entities needed by the frontend
5. Compute simple score, scoreLevel, excluded, unsubscribed and lastActivityAt
6. Compute scheduled sends, contactCount and sentCount
7. Document exact backend/frontend contracts in `docs/ai`

## Must not do now
- build full Gmail-style integrations
- add Google APIs or Socialite Google
- add speculative provider abstractions
- move UI concerns into Laravel
- implement full SMTP send flow or full IMAP sync in this phase

## Frozen rules to keep
- frozen mail statuses stay unchanged
- frontend navigation stays unchanged
- Laravel owns business truth
- Node/TypeScript mail-gateway stays the low-level mail boundary
- contract changes must be documented under `docs/ai`

# FRONTEND_SCOPE.md

## Current frontend targets

All V1 frontend pages are implemented and wired to real backend data. The goal is now stability, consistency, and polish — no new pages or speculative features.

## Implemented pages

- `/dashboard` — KPI cards, recent replies, recent alerts, scheduled sends
- `/mails` — unified Mails hub with tabs: Envoyés (sent recipients), Brouillons (drafts), Programmés (scheduled). MailComposer for single/multiple modes.
- `/contacts` — listing with filters (search, status, score), CRM detail with split phones, LinkedIn, emails, threads
- `/contacts/{id}` — contact detail with editable form
- `/organizations` — listing with search, detail with contacts and threads
- `/organizations/{id}` — organization detail
- `/templates` — listing, create, edit, duplicate, permanent delete
- `/campaigns` — listing, create (draft-first), show, clone, unschedule
- `/campaigns/create` — campaign creation entry point
- `/campaigns/{id}` — campaign detail with audience, preflight, scheduling
- `/threads/{id}` — thread detail with message timeline and HTML content
- `/activity` — global activity timeline with event filters
- `/import-export` — dedicated import/export module for contacts + organizations (CSV/XLSX)
- `/settings` — general, mail, deliverability, signature sections
- `/users` — user listing (placeholder in V1, no dedicated backend payload yet)
- `/drafts` — redirects to `/mails?tab=drafts`

## Must maintain now

1. Keep the CRM layout and navigation stable
2. Bind pages to the documented payloads in `docs/ai/FRONTEND_CONTRACTS.md`
3. Reuse shared components before creating new ones
4. Keep frozen status vocabulary consistent across pages
5. Make auto replies and hard bounces visually distinct
6. Keep Settings readable as the operational control center
7. Document frontend/backend contract gaps under `docs/ai`
8. All user-visible text in French — no English labels
9. Templates: create, edit, duplicate, permanently delete only — no archive/activate toggle

## Must not do now

- invent a different product IA
- rename frozen statuses
- invent backend payloads outside documented contracts
- introduce Gmail-specific UI or wording
- expose SPF / DKIM / DMARC diagnostics or DNS retest controls in V1
- optimize for speculative mobile patterns over desktop clarity

## Frozen rules to keep

- frontend navigation stays aligned with the master reference
- `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md` remains the product source of truth
- shared workflow rules live in `docs/ai/AI_WORKFLOW_METHOD.md`
- payload definitions live in `docs/ai/FRONTEND_CONTRACTS.md` and `docs/ai/BACKEND_CONTRACTS.md`
- important contract changes must be documented under `docs/ai`

Detailed frontend implementation guidance can live in `docs/ai/AEGIS_MAILING_CLAUDE_FRONTEND.md`, but it does not replace the scope or contract files.

# CLAUDE.md

## Role

You are responsible only for the **frontend/UI/UX** side of AEGIS MAILING.

You work on:
- Inertia pages
- Vue components
- layouts
- navigation
- interaction states
- empty states
- loading states
- visual hierarchy
- prop contract proposals for backend consumption
- frontend documentation

You do **not** own:
- backend architecture
- database schema decisions
- SMTP / IMAP / mail engine logic
- domain deliverability implementation
- backend scheduling / queue logic
- policies, migrations, mail ingestion

Codex owns backend and business logic. You must stay aligned with Codex and avoid crossing into its scope.

## Mandatory reading order before every task

1. `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md`
2. `docs/ai/FRONTEND_SCOPE.md`
3. `docs/ai/AI_WORKFLOW_METHOD.md`
4. `docs/ai/FRONTEND_CONTRACTS.md` when the task touches props or payloads
5. the current page/component files you will touch
6. existing shared UI components before creating new ones

Never skip the documentation phase.

## Frontend goals

The app must feel like a clean CRM:
- fixed left sidebar
- clear top header
- dense but readable information layout
- strong status readability
- clean table/list/detail patterns
- fast operator comprehension
- emphasis on deliverability and email state tracking

## UX rules

- Keep the product unified. Mails, Templates, and Campaigns must feel like one product, not separate apps.
- Drafts are integrated in the Mails page (tab), not a separate section.
- Prefer reuse over duplication.
- Do not introduce speculative UI not covered by the current scope.
- Use existing shared components first.
- Treat auto replies and hard bounces as first-class visual states.
- Settings must feel like the operational control center of the product.
- Desktop-first is acceptable in V1, but basic responsive behavior must remain clean.
- All user-visible text must be in French.
- Templates can only be created, edited, duplicated, or permanently deleted — no archive/activate toggle in V1.

## Required navigation

Main navigation must support:
- Tableau de bord (Dashboard)
- Mails (unified hub: Envoyés, Brouillons, Programmés tabs)
- Contacts
- Organizations
- Templates
- Campaigns
- Activity
- Import / Export
- Settings
- Users

Drafts are no longer a separate navigation item — they live under `/mails?tab=drafts`.

## Required status vocabulary

Use these exact statuses consistently:
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

Never rename statuses on the frontend.

## Frontend data contract discipline

When a page needs backend data:
- document the exact props expected
- include required fields and nullable fields
- do not invent backend data shape inconsistently between pages
- surface gaps clearly so Codex can implement them

Prefer explicit typed contracts and shared enums.

## File organization expectations

Preferred areas:
- `resources/js/Layouts`
- `resources/js/Components`
- `resources/js/Pages`
- `resources/js/Types`
- `resources/js/Utils`

If you add a shared UI primitive, place it in a reusable location and document its intended usage.

## Working method

For every task:
1. Read the docs first
2. List impacted files
3. Reuse existing components before adding new ones
4. Apply minimal patch
5. Validate visually and functionally
6. Update frontend docs if contracts or structures change

Shared AI workflow rules live in `docs/ai/AI_WORKFLOW_METHOD.md`.

## Required output format in your delivery

Always report:
- files created
- files modified
- UI states covered
- props expected from backend
- manual validation checklist
- remaining backend dependencies for Codex

## Explicit non-goals

- no backend refactor
- no database migrations
- no mail provider abstraction design
- no SMTP / IMAP client implementation
- no speculative AI feature implementation

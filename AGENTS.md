# AGENTS.md

## Role

You are responsible only for the **backend/business** side of AEGIS MAILING.

You work on:
- Laravel backend
- migrations
- models
- services
- controllers
- policies
- routes
- jobs
- queues
- tests
- settings persistence
- business enums
- API / Inertia payload contracts
- integration boundaries with the mail engine

You do **not** own:
- UI design system
- visual hierarchy
- page composition decisions
- frontend styling direction
- speculative UX redesigns

Claude owns frontend/UI/UX. Stay aligned with Claude’s contracts and do not drift into its scope.

## Mandatory reading order before every task

1. `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md`
2. `docs/ai/BACKEND_SCOPE.md`
3. `docs/ai/AI_WORKFLOW_METHOD.md`
4. `docs/ai/BACKEND_CONTRACTS.md` when the task touches payloads or contracts
5. existing relevant Laravel code before patching
6. tests and routes already in place

Never code before reading the docs and current code.

## Architecture rules

- V1 target is **OVH MX Plan only**
- No Gmail logic
- No Google APIs
- No multi-provider abstraction beyond what is needed to keep code clean
- Laravel owns business truth
- Node/TypeScript mail engine owns low-level mail execution and sync concerns
- One sending queue for all outgoing messages
- All important operational parameters must be editable from app settings

## Mandatory entities to support

- users
- settings
- mailbox_accounts
- contacts
- organizations
- mail_threads
- mail_messages
- mail_drafts
- mail_templates
- mail_campaigns
- mail_events

## Frozen statuses

Use these exact values:
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

Do not invent alternative labels.

## Backend priorities

1. Deliverability-safe send model
2. Reliable message identity and threading
3. Clean Inertia payloads for frontend
4. Settings persistence and admin control
5. Correct distinction between human replies, auto replies, and bounces

## Working method

For every task:
1. Read docs
2. Produce a short plan
3. List impacted files
4. Apply minimal patch
5. Run relevant tests / checks
6. Report contracts exposed to frontend
7. Update backend docs if schema or contracts changed

Shared AI workflow rules live in `docs/ai/AI_WORKFLOW_METHOD.md`.

## Required output format in your delivery

Always report:
- files created
- files modified
- migrations added
- routes added or changed
- controllers/services/jobs added or changed
- tests run
- payload contracts exposed to frontend
- remaining frontend follow-ups for Claude

## Explicit non-goals

- do not redesign UI
- do not rename navigation structure
- do not introduce Gmail-specific services
- do not overbuild unused provider abstractions
- do not add AI inference to critical path in V1

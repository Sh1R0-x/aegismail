# FRONTEND_SCOPE.md

## Current frontend targets

The immediate goal is to support the current CRM frontend foundation with stable pages, shared components, and predictable backend contracts while staying inside the frozen V1 scope.

## Current phase

This phase focuses on the currently wired frontend pages:
- `/dashboard`
- `/contacts`
- `/organizations`

The rest of the main navigation must remain visible and consistent even when some sections are still placeholders.

## Must implement now
1. Keep the CRM layout and navigation stable
2. Bind pages to the documented payloads in `docs/ai/FRONTEND_CONTRACTS.md`
3. Reuse shared components before creating new ones
4. Keep frozen status vocabulary consistent across pages
5. Make auto replies and hard bounces visually distinct
6. Keep Settings readable as the operational control center
7. Document frontend/backend contract gaps under `docs/ai`

## Must not do now
- invent a different product IA
- rename frozen statuses
- invent backend payloads outside documented contracts
- introduce Gmail-specific UI or wording
- optimize for speculative mobile patterns over desktop clarity

## Frozen rules to keep
- frontend navigation stays aligned with the master reference
- `docs/ai/AEGIS_MAILING_MASTER_REFERENCE.md` remains the product source of truth
- shared workflow rules live in `docs/ai/AI_WORKFLOW_METHOD.md`
- payload definitions live in `docs/ai/FRONTEND_CONTRACTS.md` and `docs/ai/BACKEND_CONTRACTS.md`
- important contract changes must be documented under `docs/ai`

Detailed frontend implementation guidance can live in `docs/ai/AEGIS_MAILING_CLAUDE_FRONTEND.md`, but it does not replace the scope or contract files.

# DECISIONS_LOG.md

## Current frozen decisions
- One mailbox in V1
- OVH MX Plan only
- No Gmail logic
- One queue for simple and multiple mail
- Global signature
- Reusable templates in V1
- Editable drafts before scheduling
- Auto-reply handling in V1
- Default daily target around 100 emails/day
- Daily ceiling editable in settings
- Deliverability is a top-level product concern
- Draft recipients are stored in `mail_drafts.payload_json.recipients` before scheduling
- Scheduling creates deliverable `mail_recipients` only after preflight passes

## Documentation alignment
- `docs/ai/FRONTEND_SCOPE.md` is the canonical frontend scope file used by `CLAUDE.md`.
- `AI_COORDINATION_TREE.md` and the detailed frontend/backend annexes live under `docs/ai`.

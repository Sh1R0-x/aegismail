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
- Scheduling creates per-recipient `mail_threads` and `mail_messages` before queue dispatch
- All outbound dispatches still use the single queue `mail-outbound`
- Auto-stop in this phase is a simple threshold check on failed and hard-bounced recipients
- IMAP sync V1 is limited to `INBOX` and `SENT`
- IMAP sync resume is driven by mailbox UID cursors stored on `mailbox_accounts`
- IMAP sync is protected by a mailbox+folder lock
- Thread resolution order is frozen: `In-Reply-To` -> `References` -> known `Message-ID` -> cautious heuristic -> new thread
- `auto_reply`, `out_of_office` and `auto_ack` remain distinct from human replies
- `hard_bounce` remains distinct from `soft_bounce` and updates exclusion state
- Activity timeline is fed from persisted `mail_messages`, not from speculative frontend state
- Local smoke/E2E validation uses Playwright with a dedicated seeded SQLite database and no Docker/Sail
- Full OVH production realism for V1 means a VPS baseline; OVH mutualized is only acceptable for a degraded or demo mode
- Drafts and templates are text-first in V1: `text_body` / `text_template` can be the primary authored content, `html_*` stays optional, and Laravel synthesizes a minimal HTML body at dispatch time when only text is provided
- Preflight blocks scheduling when both text and HTML bodies are empty

## Documentation alignment
- `docs/ai/FRONTEND_SCOPE.md` is the canonical frontend scope file used by `CLAUDE.md`.
- `AI_COORDINATION_TREE.md` and the detailed frontend/backend annexes live under `docs/ai`.

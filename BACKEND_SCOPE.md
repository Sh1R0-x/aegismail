> Legacy location. The canonical backend scope lives in `docs/ai/BACKEND_SCOPE.md`.

# BACKEND_SCOPE.md

## Current backend targets

The immediate goal is to support the existing frontend foundation with real business data contracts while staying inside the frozen V1 scope.

## Must implement now
1. Freeze shared enums for statuses and message types
2. Provide Inertia payloads for the current pages
3. Create or align settings persistence for:
   - mail
   - deliverability
   - cadence
   - scoring
   - signature
4. Add missing entities needed by the current UI roadmap:
   - contacts
   - organizations
   - mail_threads
   - mail_messages
   - mail_drafts
   - mail_templates
   - mail_campaigns
   - mail_events
5. Prepare mailbox configuration around a single OVH MX Plan mailbox
6. Support clear status separation for:
   - human reply
   - auto reply
   - soft bounce
   - hard bounce
   - unsubscribe
   - failed
7. Expose payloads that are stable enough for Claude to bind against

## Must not do now
- build full Gmail-style integrations
- over-engineer provider abstraction
- mix UI work into backend tasks
- add speculative AI features

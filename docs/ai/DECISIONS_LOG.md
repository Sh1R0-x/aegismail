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
- Campaign clone (`POST /api/campaigns/{campaign}/clone`) creates a new draft campaign with name suffixed by "(copie)", resets status to `draft`, clears send metrics and scheduling data (execution history is not copied); the clone action is available from both the campaign list and the campaign detail page; on success the UI redirects to the new campaign detail with `?cloned=1` which triggers a success banner on arrival
- Preflight blocks scheduling when both text and HTML bodies are empty
- Contacts and organizations are creatable in V1 through Laravel API endpoints; page payloads expose explicit `capabilities.canCreate` and `capabilities.createEndpoint`
- Campaign creation stays technically `draft-first` in V1, but the visible operator entry point is now `/campaigns/create`; draft remains an internal technical layer and should no longer be the visible product destination for “create campaign”
- Campaign editing now exposes a backend autosave contract; the internal draft remains, but business flow must no longer depend on a manual "save draft" action
- Contact imports are preview-first in V1 when possible: Laravel validates rows before confirmation, requires `organization_name` and `primary_email`, and persists recent import batches for campaign audience reuse
- Organizations are mandatory for manual contact creation and contact imports; deleting an organization with attached contacts is now blocked server-side
- Deliverability checks are now persisted as structured per-mechanism payloads (`spf`, `dkim`, `dmarc`) with manual refresh and technical logs
- Fake CTAs are not acceptable in V1: any visible action must now be either functional, explicitly unavailable with a reason, or removed from the UI
- The current repo still has no coherent RBAC layer or admin policy system; backend create access is not blocked by roles in this phase
- Templates can now be permanently deleted (not just archived); linked drafts have their `template_id` set to null before deletion
- Immediate send ("Envoyer maintenant") uses the same pipeline as scheduled send but with `scheduledAt = now()`; no separate dispatch path
- Test send dispatches a single message via the gateway client without creating `MailRecipient` or `MailMessage` records; subject is prefixed with `[TEST]`
- Application timezone is `Europe/Paris` (changed from UTC); all serializer dates use `config('app.timezone')` for consistent local display
- Campaign Show page exposes an "unschedule" button when status is `scheduled`, calling the existing `POST /api/drafts/{draft}/unschedule` endpoint
- Templates archive/activate UI removed in V1 closure pass; templates can only be created, edited, duplicated, or permanently deleted — no soft-archive toggle
- Error messages referencing "preflight" in user-visible French text replaced with "vérification" for consistency with button labels
- Gateway driver (`stub` vs `http`) is now exposed as an Inertia shared prop `gatewayDriver` on all pages; `CrmLayout` shows a permanent amber warning banner when running in stub mode
- `POST /api/drafts/{draft}/send-now` and `POST /api/drafts/{draft}/schedule` JSON responses now include a `driver` field so the frontend can surface driver awareness at scheduling time
- Real OVH MX Plan send was validated end-to-end: Laravel → HttpMailGatewayClient → Node gateway → ssl0.ovh.net:465 → delivery to external inbox (ludovic.bellavia@gmail.com), SMTP response `250 2.0.0 Ok`
- SMTP credentials for real sends live exclusively on `mailbox_accounts` (columns: `username`, `password_encrypted`, `smtp_host`, `smtp_port`, `smtp_secure`); there are no global SMTP credentials in the `settings` table
- All backend date serialization now uses ISO 8601 (`->toIso8601String()`) with `Europe/Paris` timezone; the frontend formats dates using a shared `formatDateFR()` utility producing `dd/mm/yyyy - HHhMM` format
- Brouillons (Drafts) is no longer a separate navigation item or page; `/drafts` redirects to `/mails?tab=drafts`
- The Mails page is the unified operational hub with three tabs: Envoyés (recipients), Brouillons (drafts), Programmés (scheduled)
- `ComposerPageDataService::mails()` now returns `drafts[]` alongside `recipients[]` and includes `contactName` and `organization` per recipient
- `MailboxActivityService::thread()` now serializes `htmlBody` and `textBody` for each message, enabling real mail content reading in thread detail
- `CrmManagementService::deleteContactEmail()` now nullifies `mail_recipients.contact_email_id` before deletion to prevent dangling references
- Organization thread serialization now includes `replyReceived` and `autoReplyReceived` fields for consistency with contact and activity views
- Switching from stub to real sends requires only `MAIL_GATEWAY_DRIVER=http` in `.env` and the Node mail-gateway running on port 3001; no code changes needed
- `DraftService::testSend()` must use `MailboxSettingsService::getConnectionConfiguration()` for SMTP credentials (username, password, host, port, secure); `getSettings()` only exposes `mailbox_password_configured: bool` for frontend display — it never returns the actual decrypted password
- Default local environment is now `MAIL_GATEWAY_DRIVER=http` (real sends); `stub` is reserved for automated tests only (`e2e-serve.ps1` forces stub)
- `scripts/dev.ps1` now auto-starts mail-gateway (Node, port 3001) and queue worker (`mail-outbound,mail-sync`) alongside Laravel and Vite; all four services managed as a single dev stack
- Send window defaults remain `09:00–18:00`; `sendNow` at night schedules for the next morning — this is intentional for deliverability. Operators who need to send outside the window must adjust settings

## Documentation alignment

- `docs/ai/FRONTEND_SCOPE.md` is the canonical frontend scope file used by `CLAUDE.md`.
- `AI_COORDINATION_TREE.md` and the detailed frontend/backend annexes live under `docs/ai`.

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
- Contact imports are preview-first in V1: Laravel accepts CSV/XLSX uploads, auto-maps French/legacy aliases, never writes during dry-run, uses exact email as the only default update key, and protects confirmation with a single-use preview token
- Import / Export is now a dedicated backend module for contacts + organizations: the same stable CSV shape is used for template download, full export, preview, and confirmation so operators can round-trip `export -> modify -> reimport` without changing format
- Import preview now exposes per-row organization/contact diffs with `create|update|unchanged|skip|error`; exact email can therefore resolve either to a prudent update or to an explicit no-op (`unchanged`)
- Contacts now persist split phones (`phone_landline`, `phone_mobile`) while keeping legacy `phone` populated for backward compatibility
- Organizations are mandatory for manual contact creation and contact imports; deleting an organization with attached contacts is now blocked server-side
- DNS authentication (SPF / DKIM / DMARC) remains a prerequisite managed outside the application; V1 no longer exposes embedded DNS diagnostics or manual refresh in Settings
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
- Public outbound URLs now resolve from `settings.deliverability.public_base_url` first, then `MAIL_PUBLIC_BASE_URL`, then `APP_URL`; tracking/unsubscribe URLs resolve from `settings.deliverability.tracking_base_url` first, then `MAIL_TRACKING_BASE_URL`, then the resolved public base
- Localhost, loopback, private/reserved IPs and reserved local hostnames/TLDs are now rejected for outbound email links, tracking pixels, remote images and unsubscribe URLs
- Outbound messages now always leave Laravel with both `text/plain` and `text/html`; the missing sibling version is synthesized from the authored body when needed
- Bulk sends now require a public HTTPS base and emit one-click unsubscribe headers backed by `/u/{token}`
- The Node gateway must strip internal Laravel metadata headers (`tracking`, `gateway`, `gateway_error`) before SMTP submission so only transport-safe headers are emitted on the wire
- Settings page now has a "Délivrabilité" section (5th nav item) implemented in `SettingsDeliverability.vue`; it fetches its own data from `GET /api/settings` on mount and saves via `PUT /api/settings/deliverability`; no Inertia prop needed from Laravel
- `settings.deliverability.publicBaseUrl`, `trackingBaseUrl`, and their `*Status` / `*Issue` counterparts are now visible and actionable in the Deliverability settings section with status badges and issue alerts
- `PreflightResult.vue` now shows a "→ Corriger dans Réglages › Délivrabilité" guide note for all URL-related blocking codes (`link_*`, `image_*`, `tracking_base_url_invalid`, `bulk_unsubscribe_unavailable`)
- `hasTextVersion` in the preflight result now shows an "inclus dans MIME" sub-label when true to reflect that the backend guarantees a real text/plain in the outbound message

## Import / Export module — frontend

- Import / Export is now a dedicated entry in the main navigation sidebar, between Activity and Settings
- Route: `GET /import-export` renders `ImportExport/Index.vue`
- The existing `/contacts/imports` route remains for backwards compatibility but the primary UX entry point is the new module
- The Contacts page header link now points to `/import-export` with label "Import / Export"
- The Import / Export page exposes: template export, data export, CSV upload, preview/diff with per-row action badges (create/update/unchanged/skip/error), organization/contact breakdown, column mapping display, error/warning banners, diff filtering, confirmation with previewToken, and result summary
- Contacts list now displays LinkedIn (clickable), phone_landline, and phone_mobile columns
- Backend API endpoints consumed: `GET /api/import-export/template`, `GET /api/import-export/export`, `POST /api/import-export/preview`, `POST /api/import-export/confirm`
- All labels are in French
- The previewToken expiry / already-consumed case is handled with a dedicated error message

## Stabilization pass — transverse fixes

- `CrmPageDataService::mapContact()` no longer falls back `phoneLandline` to the legacy `phone` field; `phoneMobile` is deduplicated against `phoneLandline` at backend level. Frontend also guards display: if `phoneMobile === phoneLandline`, only one is shown
- `CrmPageDataService::describeAlert()` now outputs French labels: "Rebond temporaire", "Rebond permanent" instead of "Soft bounce", "Hard bounce"
- `CrmPageDataService` — `recentReplies` and `recentAlerts` limits raised from 5 to 10; `recentAlerts` now includes `failed` classification
- `ComposerPageDataService::mails()` — recipients in Envoyés tab now filtered with `whereNotIn('status', ['draft', 'scheduled', 'queued'])` to exclude pre-send statuses
- `DraftService::serialize()` now includes full `attachments[]` array `{ id, name, size, mimeType }` alongside existing `attachmentCount`
- New API routes added: `POST /api/drafts/bulk-delete` (safer than DELETE with body), `POST /api/drafts/{draft}/attachments`, `DELETE /api/drafts/{draft}/attachments/{attachment}`, `GET /api/contacts/search?q=`
- Contact search endpoint returns max 20 matches (name, email, org), min 2 chars, contacts without email excluded
- Draft attachment upload: max 10 MB per file, stored on local disk, auto-creates directory
- Cancel button on contact detail page (`Contacts/Show.vue`) now navigates back to `/contacts` instead of only resetting the form
- StatusBadge.vue, filter dropdowns, settings labels: all `Soft bounce` → `Rebond temporaire`, `Hard bounce` → `Rebond permanent`, `bounce rate` → `taux de rebond`, `Bounces` → `Rebonds`
- CampaignAudiencePicker default tab changed from `contacts` to `organizations`
- MailComposer.vue: mode description text added ("Un seul destinataire — message personnel" vs "Plusieurs destinataires — chacun reçoit un mail individuel")
- MailComposer.vue: contact search autocomplete added to single recipient field (debounced 300ms, calls `GET /api/contacts/search`)
- MailComposer.vue: file attachment upload section replaces placeholder note; auto-saves draft before first upload if needed
- MailComposer.vue: quick scheduling buttons added (Aujourd'hui 9h/14h, Demain 9h/14h, Après-demain 9h)
- Mails/Index.vue: bulk-delete now uses `POST /api/drafts/bulk-delete` instead of `DELETE /api/drafts` with body; all silent catch blocks replaced with error banners
- All French translation audit complete: no remaining English UI labels in Vue components
- E2E smoke navigation test updated to include the Import / Export route

## Security hardening pass

- API routes now have global rate limiting (`throttle:60,1` via `throttleApi`)
- `POST /api/drafts/{draft}/test-send` rate-limited to 5 requests per minute to prevent mail flood abuse
- `POST /api/settings/mail/test-imap` and `test-smtp` rate-limited to 5 requests per minute
- Public tracking endpoints (`/t/o/{token}.gif`, `/t/c/{token}`) rate-limited to 120 and 60 requests per minute respectively
- Public unsubscribe endpoint (`/u/{token}`) rate-limited to 10 requests per minute
- Inbound email HTML is sanitized via DOMPurify before rendering with `v-html` in Threads/Show.vue — prevents stored XSS from malicious email content
- Draft attachment uploads now validate MIME types (whitelist: pdf, doc, docx, xls, xlsx, csv, txt, rtf, odt, ods, jpg, jpeg, png, gif, webp, svg, zip)
- HTML body and text body fields now have explicit max size limits (1 MB HTML, 500 KB text) in UpsertDraftRequest and AutosaveCampaignRequest
- Signature fields capped at 50 KB in MailSettingsRequest
- Deliverability settings URL fields now validate as HTTPS URLs (`url:https`) instead of plain strings
- Draft recipient email fields now validate as RFC-compliant email addresses instead of plain strings
- Authorization remains `return true` on all form requests — intentional for V1 single-operator model (no RBAC layer)

## Dead code cleanup pass

- Template archive/activate API routes removed (`POST /api/templates/{template}/archive`, `/activate`) — these were dead since the V1 closure pass removed the UI toggle
- Legacy `DELETE /api/drafts` bulk delete route removed — superseded by `POST /api/drafts/bulk-delete`
- Drafts/Index.vue bulk delete updated to use POST route for consistency
- `completed` status entry removed from StatusBadge.vue — not part of the frozen status vocabulary
- Missing error handling added to Templates/Index.vue for duplicate and delete operations
- Missing query indexes added via migration: `mail_recipients.contact_id`, `mail_recipients.contact_email_id`, `mail_threads.contact_id`, `mail_threads.organization_id`

## Documentation alignment

- `docs/ai/FRONTEND_SCOPE.md` is the canonical frontend scope file used by `CLAUDE.md`.
- `AI_COORDINATION_TREE.md` and the detailed frontend/backend annexes live under `docs/ai`.

## Phase 2 — Product/UX/data consistency pass

- Contact search autocomplete in MailComposer.vue now reads `data.contacts` (was `data.results`); matches backend `ContactController::search()` response key
- Contact detail form (`Contacts/Show.vue`) now exposes split phone fields: "Téléphone fixe" (`phoneLandline`) and "Téléphone mobile" (`phoneMobile`) replacing the single "Téléphone" field, plus a "LinkedIn" URL field — all three fields already accepted by backend `UpdateContactRequest`
- `serializeContactDetail()` now deduplicates `phoneMobile`: returns `null` when mobile equals landline, consistent with `mapContact()` list serialization
- Sidebar navigation label changed from "Dashboard" to "Tableau de bord" for French consistency
- TimelineEntry badges translated: "Auto" → "Réponse auto", "Bounce" → "Rebond"
- Dashboard health status mapping: `mapHealthStatus()` now maps `null`/unknown to `'unknown'` instead of `'critical'`; frontend `Dashboard.vue` renders "Non évaluée" with neutral slate styling for unknown state, reserving "Critique" for explicitly critical health
- Sent tab status filter dropdown: removed orphan "Planifiés" option that could never match items (scheduled recipients are excluded from the sent query by the backend)
- Cancel button on Contact Show, draft deletion, attachment flow, scheduling UX, simple/multiple/campaign distinction, CampaignAudiencePicker org default, import/export round-trip: all verified working correctly — no fixes needed
- Test updated: `CrmPagePayloadTest::test_dashboard_page_exposes_the_empty_payload_shape` now expects `healthStatus: 'unknown'` instead of `'critical'` for unconfigured mailbox

## Phase 3 — Documentation overhaul and final product audit

- `CrmPageDataService::mapContact()` phoneMobile dedup aligned with `serializeContactDetail()`: now compares against `phone_landline ?: phone` instead of `phone_landline` alone, preventing false positive phoneMobile display when phone_landline is empty but phone equals phone_mobile
- New test: `test_contacts_list_deduplicates_phone_mobile_when_phone_landline_is_empty` covers the edge case where phone_landline is null and phone_mobile equals legacy phone
- CLAUDE.md overhauled: navigation list updated (Drafts removed as separate item, Import/Export added, "Tableau de bord" label), UX rules updated (French-first, template delete-only, Mails hub)
- FRONTEND_SCOPE.md rewritten: "Current phase" replaced with exhaustive list of all 16 implemented pages, "Must implement now" replaced with "Must maintain now"
- BACKEND_SCOPE.md rewritten: "Current phase" replaced with exhaustive list of all implemented backend features, test count updated
- FRONTEND_CONTRACTS.md: healthStatus enum updated to include `unknown` value
- BACKEND_CONTRACTS.md: removed duplicate `/drafts` redirect entry
- AEGIS_MAILING_CLAUDE_FRONTEND.md: navigation updated (Brouillons removed as separate entry, Import/Export added, "Tableau de bord" label), Modèles section corrected (suppression permanente instead of activation/archivage)
- AEGIS_MAILING_CODEX_BACKEND.md: contacts schema updated to include phone_landline, phone_mobile, linkedin_url, country, city, tags_json
- All 8 priority bugs from Phase 3 audit verified:
  1. Sent tab safe from draft status — `whereNotIn` filter confirmed
  2. Dashboard data populated from real DB queries — recentReplies, recentAlerts, scheduledSends all functional
  3. French translations complete — zero English user-visible text
  4. Draft deletion has proper guard against sent history + DB transaction cascade
  5. Single/multiple/campaign has real business logic differentiation (mode stored on draft+campaign)
  6. Phone dedup now consistent across list and detail views
  7. Import/export round-trip verified working — tests cover unchanged, update, and create scenarios
  8. Documentation overhaul complete — all docs/ai files aligned with current codebase

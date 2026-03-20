<?php

namespace App\Services\Mailing\Composer;

use App\Models\ContactEmail;
use App\Models\MailboxAccount;
use App\Models\MailDraft;
use App\Services\Mailing\EmailContentService;
use App\Services\Mailing\PublicEmailUrlService;
use App\Services\Mailing\SmtpProviderService;
use App\Services\SettingsStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DraftPreflightService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly EmailContentService $emailContentService,
        private readonly PublicEmailUrlService $publicEmailUrlService,
        private readonly SmtpProviderService $smtpProviderService,
    ) {}

    public function run(MailDraft $draft, ?MailboxAccount $mailbox, ?string $outboundProvider = null): array
    {
        $provider = $outboundProvider ?: $draft->outbound_provider ?: $this->smtpProviderService->activeProvider();
        $providerSnapshot = $this->smtpProviderService->providerPayload($provider, $mailbox);
        $deliverabilitySettings = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $resolvedRecipients = $this->resolvedRecipients($draft);
        $deliverableRecipients = $resolvedRecipients->where('deliverable', true)->values();
        $optOutRecipients = $resolvedRecipients->where('opted_out', true)->values();
        $excludedRecipients = $resolvedRecipients->where('excluded', true)->values();
        $invalidRecipients = $resolvedRecipients->where('invalid', true)->values();
        $optOutCount = $optOutRecipients->count();
        $excludedCount = $excludedRecipients->count();
        $invalidCount = $invalidRecipients->count();
        $attachments = $draft->attachments;
        $mailSettings = $this->settingsStore->get('mail', config('mailing.defaults.mail', []));
        $preparedBodies = $this->emailContentService->prepareBodies(
            $draft->html_body,
            $draft->text_body,
            $draft->signature_snapshot ?: ($mailSettings['global_signature_html'] ?? null),
            $mailSettings['global_signature_text'] ?? null,
        );
        $analysis = $preparedBodies['analysis'];
        $htmlBody = (string) ($preparedBodies['html_body'] ?? '');
        $textBody = (string) ($preparedBodies['text_body'] ?? '');
        $linkCount = (int) $analysis['linkCount'];
        $remoteImageCount = (int) $analysis['remoteImageCount'];
        $attachmentBytes = (int) $attachments->sum('size_bytes');
        $estimatedWeightBytes = strlen((string) $draft->subject) + strlen($htmlBody) + strlen($textBody) + $attachmentBytes;
        $mailboxValid = $this->mailboxValid($mailbox, $providerSnapshot);
        $hasTextVersion = (bool) $analysis['hasTextVersion'];

        $errors = [];
        $warnings = [];

        if (! $mailboxValid) {
            $errors[] = [
                'code' => 'mailbox_invalid',
                'message' => "Le contexte d’envoi {$providerSnapshot['label']} n’est pas prêt pour la planification.",
            ];
        }

        if ($deliverableRecipients->isEmpty()) {
            $errors[] = [
                'code' => 'no_deliverable_recipients',
                'message' => 'Aucun destinataire exploitable n’est disponible pour cet envoi.',
            ];
        }

        if (! filled(trim((string) $draft->html_body)) && ! filled(trim((string) $draft->text_body))) {
            $errors[] = [
                'code' => 'missing_message_body',
                'message' => 'Le message est vide. Ajoutez au moins une version texte ou HTML avant la planification.',
            ];
        }

        $this->appendContentIssueErrors($errors, $analysis['issues']);

        $trackingRequired = ((bool) ($deliverabilitySettings['tracking_opens_enabled'] ?? true) && filled(trim($htmlBody)))
            || ((bool) ($deliverabilitySettings['tracking_clicks_enabled'] ?? true) && $linkCount > 0);

        if ($trackingRequired && $this->publicEmailUrlService->trackingBaseUrl() === null) {
            $errors[] = [
                'code' => 'tracking_base_url_invalid',
                'message' => 'Le tracking sortant exige une URL publique HTTPS. Configurez `settings.deliverability.tracking_base_url`, `public_base_url` ou un `APP_URL` public.',
            ];
        }

        if ($draft->mode === 'bulk' && $this->publicEmailUrlService->trackingBaseUrl() === null) {
            $errors[] = [
                'code' => 'bulk_unsubscribe_unavailable',
                'message' => 'Les campagnes bulk exigent une URL publique HTTPS pour l’en-tête de désinscription.',
            ];
        }

        if ($remoteImageCount > 0) {
            $warnings[] = [
                'code' => 'remote_images_detected',
                'message' => 'Des images distantes sont présentes dans le HTML.',
            ];
        }

        if ($linkCount > ($deliverabilitySettings['max_links_warning_threshold'] ?? 8)) {
            $warnings[] = [
                'code' => 'too_many_links',
                'message' => 'Le nombre de liens dépasse le seuil de vigilance.',
            ];
        }

        if ($remoteImageCount > ($deliverabilitySettings['max_remote_images_warning_threshold'] ?? 3)) {
            $warnings[] = [
                'code' => 'too_many_remote_images',
                'message' => 'Le nombre d’images distantes dépasse le seuil de vigilance.',
            ];
        }

        if ($estimatedWeightBytes > (($deliverabilitySettings['html_size_warning_kb'] ?? 100) * 1024)) {
            $warnings[] = [
                'code' => 'html_weight_high',
                'message' => 'Le poids estimé du message dépasse le seuil HTML configuré.',
            ];
        }

        if ($attachmentBytes > (($deliverabilitySettings['attachment_size_warning_mb'] ?? 10) * 1024 * 1024)) {
            $warnings[] = [
                'code' => 'attachments_weight_high',
                'message' => 'Le poids cumulé des pièces jointes dépasse le seuil configuré.',
            ];
        }

        return [
            'ok' => count($errors) === 0,
            'provider' => $provider,
            'providerLabel' => $providerSnapshot['label'],
            'providerReady' => (bool) $providerSnapshot['ready'],
            'mailboxValid' => $mailboxValid,
            'hasTextVersion' => $hasTextVersion,
            'hasRemoteImages' => $remoteImageCount > 0,
            'estimatedWeightBytes' => $estimatedWeightBytes,
            'recipientSummary' => [
                'total' => $resolvedRecipients->count(),
                'deliverable' => $deliverableRecipients->count(),
                'excluded' => $excludedCount,
                'optOut' => $optOutCount,
                'invalid' => $invalidCount,
            ],
            'deliverability' => [
                'linkCount' => $linkCount,
                'remoteImageCount' => $remoteImageCount,
                'attachmentCount' => $attachments->count(),
                'attachmentSizeBytes' => $attachmentBytes,
                'htmlSizeBytes' => strlen($htmlBody),
            ],
            'errors' => $errors,
            'warnings' => $warnings,
            'deliverableRecipients' => $deliverableRecipients
                ->map(fn (array $recipient) => $this->serializeRecipient($recipient))
                ->all(),
            'excludedRecipients' => $excludedRecipients
                ->map(fn (array $recipient) => $this->serializeRecipient($recipient, ['reason' => 'hard_bounced']))
                ->all(),
            'optOutRecipients' => $optOutRecipients
                ->map(fn (array $recipient) => $this->serializeRecipient($recipient, ['reason' => 'opt_out']))
                ->all(),
            'invalidRecipients' => $invalidRecipients
                ->map(fn (array $recipient) => $this->serializeRecipient($recipient, ['reason' => 'invalid_email']))
                ->all(),
        ];
    }

    private function appendContentIssueErrors(array &$errors, array $issues): void
    {
        collect($issues)
            ->groupBy('code')
            ->each(function (Collection $group, string $code) use (&$errors): void {
                [$kind, $issue] = explode('_', $code, 2);
                $samples = $group->pluck('url')->filter()->unique()->take(2)->values()->all();

                $errors[] = [
                    'code' => $code,
                    'message' => $this->publicEmailUrlService->issueMessage($kind, $issue, $group->count(), $samples),
                ];
            });
    }

    private function serializeRecipient(array $recipient, array $extra = []): array
    {
        return array_merge([
            'email' => $recipient['email'],
            'contactId' => $recipient['contact_id'],
            'contactEmailId' => $recipient['contact_email_id'],
            'organizationId' => $recipient['organization_id'],
            'name' => $recipient['name'],
        ], $extra);
    }

    private function resolvedRecipients(MailDraft $draft): Collection
    {
        return collect($draft->payload_json['recipients'] ?? [])
            ->map(function (array $recipient): array {
                $emailRecord = $this->resolveContactEmail($recipient);
                $email = Str::lower(trim((string) ($emailRecord?->email ?? ($recipient['email'] ?? ''))));
                $invalid = $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false;
                $optedOut = $emailRecord?->opt_out_at !== null;
                $hardBounced = $emailRecord?->bounce_status === 'hard_bounced';

                return [
                    'email' => $email,
                    'name' => $recipient['name'] ?? null,
                    'contact_id' => $recipient['contactId'] ?? $emailRecord?->contact_id,
                    'contact_email_id' => $recipient['contactEmailId'] ?? $emailRecord?->id,
                    'organization_id' => $recipient['organizationId'] ?? $emailRecord?->contact?->organization_id,
                    'invalid' => $invalid,
                    'opted_out' => $optedOut,
                    'excluded' => $hardBounced,
                    'deliverable' => ! $invalid && ! $optedOut && ! $hardBounced,
                ];
            })
            ->unique(fn (array $recipient) => $recipient['email'] ?: spl_object_hash((object) $recipient))
            ->values();
    }

    private function resolveContactEmail(array $recipient): ?ContactEmail
    {
        if (! empty($recipient['contactEmailId'])) {
            return ContactEmail::query()->with('contact')->find($recipient['contactEmailId']);
        }

        if (! empty($recipient['email'])) {
            return ContactEmail::query()
                ->with('contact')
                ->whereRaw('lower(email) = ?', [Str::lower($recipient['email'])])
                ->first();
        }

        return null;
    }

    private function mailboxValid(?MailboxAccount $mailbox, array $providerSnapshot): bool
    {
        if ($mailbox === null) {
            return false;
        }

        return $mailbox->provider === $this->smtpProviderService->mailboxProvider()
            && filled($mailbox->email)
            && filled($mailbox->username)
            && filled($mailbox->password_encrypted)
            && filled($mailbox->imap_host)
            && (bool) ($providerSnapshot['ready'] ?? false);
    }
}

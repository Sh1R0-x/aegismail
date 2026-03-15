<?php

namespace App\Services\Mailing\Composer;

use App\Models\ContactEmail;
use App\Models\MailDraft;
use App\Models\MailboxAccount;
use App\Services\SettingsStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DraftPreflightService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
    ) {
    }

    public function run(MailDraft $draft, ?MailboxAccount $mailbox): array
    {
        $deliverabilitySettings = $this->settingsStore->get('deliverability', config('mailing.defaults.deliverability', []));
        $resolvedRecipients = $this->resolvedRecipients($draft);
        $deliverableRecipients = $resolvedRecipients->where('deliverable', true)->values();
        $optOutCount = $resolvedRecipients->where('opted_out', true)->count();
        $excludedCount = $resolvedRecipients->where('excluded', true)->count();
        $invalidCount = $resolvedRecipients->where('invalid', true)->count();
        $attachments = $draft->attachments;

        $htmlBody = (string) ($draft->html_body ?? '');
        $textBody = (string) ($draft->text_body ?? '');
        $linkCount = preg_match_all('/https?:\\/\\//i', $htmlBody.$textBody);
        $remoteImageCount = preg_match_all('/<img[^>]+src=["\\\']https?:\\/\\//i', $htmlBody);
        $attachmentBytes = (int) $attachments->sum('size_bytes');
        $estimatedWeightBytes = strlen((string) $draft->subject) + strlen($htmlBody) + strlen($textBody) + $attachmentBytes;
        $mailboxValid = $this->mailboxValid($mailbox);
        $hasTextVersion = filled(trim($textBody));

        $errors = [];
        $warnings = [];

        if (! $mailboxValid) {
            $errors[] = [
                'code' => 'mailbox_invalid',
                'message' => 'La boîte OVH MX Plan n’est pas prête pour la planification.',
            ];
        }

        if ($deliverableRecipients->isEmpty()) {
            $errors[] = [
                'code' => 'no_deliverable_recipients',
                'message' => 'Aucun destinataire exploitable n’est disponible pour cet envoi.',
            ];
        }

        if (! $hasTextVersion) {
            $warnings[] = [
                'code' => 'missing_text_version',
                'message' => 'La version texte est absente.',
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
                ->map(fn (array $recipient) => [
                    'email' => $recipient['email'],
                    'contactId' => $recipient['contact_id'],
                    'contactEmailId' => $recipient['contact_email_id'],
                    'organizationId' => $recipient['organization_id'],
                    'name' => $recipient['name'],
                ])
                ->all(),
        ];
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
                    'excluded' => $optedOut || $hardBounced,
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

    private function mailboxValid(?MailboxAccount $mailbox): bool
    {
        if ($mailbox === null) {
            return false;
        }

        return $mailbox->provider === config('mailing.provider')
            && $mailbox->send_enabled
            && filled($mailbox->email)
            && filled($mailbox->username)
            && filled($mailbox->password_encrypted)
            && filled($mailbox->imap_host)
            && filled($mailbox->smtp_host);
    }
}

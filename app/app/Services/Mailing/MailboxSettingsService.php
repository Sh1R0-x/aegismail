<?php

namespace App\Services\Mailing;

use App\Models\MailboxAccount;
use App\Services\SettingsStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MailboxSettingsService
{
    public function __construct(
        private readonly SettingsStore $settingsStore,
        private readonly MailEventLogger $eventLogger,
    ) {
    }

    public function getSettings(): array
    {
        $defaults = config('mailing.defaults.mail', []);
        $supplemental = $this->settingsStore->get('mail', [
            'global_signature_html' => $defaults['global_signature_html'] ?? null,
            'global_signature_text' => $defaults['global_signature_text'] ?? null,
            'send_window_start' => $defaults['send_window_start'] ?? '09:00',
            'send_window_end' => $defaults['send_window_end'] ?? '18:00',
        ]);

        $mailbox = $this->mailbox();

        $mailboxPayload = $mailbox === null
            ? []
            : [
                'provider' => $mailbox->provider,
                'sender_email' => $mailbox->email,
                'sender_name' => $mailbox->display_name,
                'mailbox_username' => $mailbox->username,
                'mailbox_password_configured' => $mailbox->getRawOriginal('password_encrypted') !== null,
                'imap_host' => $mailbox->imap_host,
                'imap_port' => $mailbox->imap_port,
                'imap_secure' => $mailbox->imap_secure,
                'smtp_host' => $mailbox->smtp_host,
                'smtp_port' => $mailbox->smtp_port,
                'smtp_secure' => $mailbox->smtp_secure,
                'sync_enabled' => $mailbox->sync_enabled,
                'send_enabled' => $mailbox->send_enabled,
                'health_status' => $mailbox->health_status,
                'health_message' => $mailbox->health_message,
                'last_sync_at' => $mailbox->last_sync_at?->toIso8601String(),
            ];

        return array_replace($defaults, $supplemental, $mailboxPayload);
    }

    public function getConnectionConfiguration(): array
    {
        $mailbox = $this->mailbox();

        if ($mailbox === null) {
            return [];
        }

        return [
            'sender_email' => $mailbox->email,
            'sender_name' => $mailbox->display_name,
            'mailbox_username' => $mailbox->username,
            'mailbox_password' => $mailbox->password_encrypted,
            'imap_host' => $mailbox->imap_host,
            'imap_port' => $mailbox->imap_port,
            'imap_secure' => $mailbox->imap_secure,
            'smtp_host' => $mailbox->smtp_host,
            'smtp_port' => $mailbox->smtp_port,
            'smtp_secure' => $mailbox->smtp_secure,
            'sync_enabled' => $mailbox->sync_enabled,
            'send_enabled' => $mailbox->send_enabled,
        ];
    }

    public function update(array $validated, ?int $updatedBy = null): array
    {
        $mailbox = $this->mailbox();
        $password = $this->resolvePassword($validated, $mailbox);

        DB::transaction(function () use ($mailbox, $password, $updatedBy, $validated): void {
            $mailbox = MailboxAccount::query()->updateOrCreate(
                ['provider' => config('mailing.provider')],
                [
                    'user_id' => $updatedBy,
                    'provider' => config('mailing.provider'),
                    'email' => $validated['sender_email'],
                    'display_name' => $validated['sender_name'],
                    'username' => $validated['mailbox_username'],
                    'password_encrypted' => $password,
                    'imap_host' => $validated['imap_host'],
                    'imap_port' => $validated['imap_port'],
                    'imap_secure' => $validated['imap_secure'],
                    'smtp_host' => $validated['smtp_host'],
                    'smtp_port' => $validated['smtp_port'],
                    'smtp_secure' => $validated['smtp_secure'],
                    'sync_enabled' => $validated['sync_enabled'],
                    'send_enabled' => $validated['send_enabled'],
                    'health_status' => $mailbox?->health_status ?? 'unknown',
                    'health_message' => $mailbox?->health_message,
                ],
            );

            $this->settingsStore->put('mail', Arr::only($validated, [
                'global_signature_html',
                'global_signature_text',
                'send_window_start',
                'send_window_end',
            ]), $updatedBy);

            $this->eventLogger->log(
                'settings.mail.updated',
                Arr::except(Arr::only($validated, [
                    'sender_email',
                    'sender_name',
                    'mailbox_username',
                    'imap_host',
                    'imap_port',
                    'imap_secure',
                    'smtp_host',
                    'smtp_port',
                    'smtp_secure',
                    'sync_enabled',
                    'send_enabled',
                    'send_window_start',
                    'send_window_end',
                ]), ['mailbox_password']),
                ['mailbox_account_id' => $mailbox->id],
            );
        });

        return $this->getSettings();
    }

    public function updateHealth(bool $healthy, string $message): ?MailboxAccount
    {
        $mailbox = $this->mailbox();

        if ($mailbox === null) {
            return null;
        }

        $mailbox->forceFill([
            'health_status' => $healthy ? 'healthy' : 'warning',
            'health_message' => $message,
        ])->save();

        return $mailbox->refresh();
    }

    private function resolvePassword(array $validated, ?MailboxAccount $mailbox): string
    {
        $provided = $validated['mailbox_password'] ?? null;

        if ($provided !== null && $provided !== '') {
            return $provided;
        }

        if ($mailbox !== null) {
            return $mailbox->password_encrypted;
        }

        throw ValidationException::withMessages([
            'mailbox_password' => ['A mailbox password is required for the OVH MX Plan account.'],
        ]);
    }

    private function mailbox(): ?MailboxAccount
    {
        return MailboxAccount::query()
            ->where('provider', config('mailing.provider'))
            ->first();
    }
}

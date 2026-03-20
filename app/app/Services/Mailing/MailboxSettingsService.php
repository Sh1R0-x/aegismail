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
        private readonly SmtpProviderService $smtpProviderService,
    ) {}

    public function getSettings(): array
    {
        $defaults = config('mailing.defaults.mail', []);
        $supplemental = $this->settingsStore->get('mail', [
            'global_signature_html' => $defaults['global_signature_html'] ?? null,
            'global_signature_text' => $defaults['global_signature_text'] ?? null,
            'send_window_start' => $defaults['send_window_start'] ?? '09:00',
            'send_window_end' => $defaults['send_window_end'] ?? '18:00',
            'active_provider' => $defaults['active_provider'] ?? $this->smtpProviderService->mailboxProvider(),
        ]);

        $mailbox = $this->mailbox();
        $activeProvider = $this->smtpProviderService->activeProvider();
        $providers = $this->smtpProviderService->providersPayload($mailbox);

        $mailboxPayload = $mailbox === null
            ? []
            : [
                'mailbox_provider' => $mailbox->provider,
                'sender_email' => $mailbox->email,
                'sender_name' => $mailbox->display_name,
                'mailbox_username' => $mailbox->username,
                'mailbox_password_configured' => $mailbox->getRawOriginal('password_encrypted') !== null,
                'imap_host' => $mailbox->imap_host,
                'imap_port' => $mailbox->imap_port,
                'imap_secure' => $mailbox->imap_secure,
                'sync_enabled' => $mailbox->sync_enabled,
                'send_enabled' => $mailbox->send_enabled,
                'health_status' => $mailbox->health_status,
                'health_message' => $mailbox->health_message,
                'last_sync_at' => $mailbox->last_sync_at?->toIso8601String(),
            ];

        return array_replace_recursive(
            $defaults,
            Arr::only($supplemental, [
                'global_signature_html',
                'global_signature_text',
                'send_window_start',
                'send_window_end',
                'active_provider',
            ]),
            $mailboxPayload,
            [
                'mailbox_provider' => $mailbox?->provider ?? $this->smtpProviderService->mailboxProvider(),
                'active_provider' => $activeProvider,
                'activeProvider' => $activeProvider,
                'active_provider_label' => $this->smtpProviderService->label($activeProvider),
                'providers' => $providers,
            ],
        );
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
            'sync_enabled' => $mailbox->sync_enabled,
            'send_enabled' => $mailbox->send_enabled,
        ];
    }

    public function update(array $validated, ?int $updatedBy = null): array
    {
        $mailbox = $this->mailbox();
        $password = $this->resolvePassword($validated, $mailbox);
        $currentMailSettings = $this->settingsStore->get('mail', config('mailing.defaults.mail', []));
        $mailSettingsPayload = array_merge(
            $this->resolveSignatureSettings($validated, $currentMailSettings),
            Arr::only($validated, [
                'send_window_start',
                'send_window_end',
                'active_provider',
            ]),
        );

        DB::transaction(function () use ($mailbox, $password, $updatedBy, $validated, $mailSettingsPayload): void {
            $mailbox = MailboxAccount::query()->updateOrCreate(
                ['provider' => $this->smtpProviderService->mailboxProvider()],
                [
                    'user_id' => $updatedBy,
                    'provider' => $this->smtpProviderService->mailboxProvider(),
                    'email' => $validated['sender_email'],
                    'display_name' => $validated['sender_name'],
                    'username' => $validated['mailbox_username'],
                    'password_encrypted' => $password,
                    'imap_host' => $validated['imap_host'],
                    'imap_port' => $validated['imap_port'],
                    'imap_secure' => $validated['imap_secure'],
                    'smtp_host' => Arr::get($validated, 'providers.ovh_mx_plan.smtp_host'),
                    'smtp_port' => Arr::get($validated, 'providers.ovh_mx_plan.smtp_port'),
                    'smtp_secure' => Arr::get($validated, 'providers.ovh_mx_plan.smtp_secure'),
                    'sync_enabled' => $validated['sync_enabled'],
                    'send_enabled' => $validated['send_enabled'],
                    'health_status' => $mailbox?->health_status ?? 'unknown',
                    'health_message' => $mailbox?->health_message,
                ],
            );

            $this->smtpProviderService->upsert($validated, $updatedBy);
            $this->smtpProviderService->validateActiveProvider($validated['active_provider'], $mailbox->fresh());
            $this->settingsStore->put('mail', $mailSettingsPayload, $updatedBy);

            $this->eventLogger->log(
                'settings.mail.updated',
                array_replace(
                    Arr::except(Arr::only($validated, [
                        'sender_email',
                        'sender_name',
                        'mailbox_username',
                        'imap_host',
                        'imap_port',
                        'imap_secure',
                        'sync_enabled',
                        'send_enabled',
                        'send_window_start',
                        'send_window_end',
                        'active_provider',
                    ]), ['mailbox_password']),
                    [
                        'providers' => [
                            'ovh_mx_plan' => Arr::except(Arr::get($validated, 'providers.ovh_mx_plan', []), ['smtp_password']),
                            'smtp2go' => Arr::except(Arr::get($validated, 'providers.smtp2go', []), ['smtp_password']),
                        ],
                    ],
                ),
                ['mailbox_account_id' => $mailbox->id],
            );
        });

        return $this->getSettings();
    }

    private function resolveSignatureSettings(array $validated, array $currentMailSettings): array
    {
        if ((bool) ($validated['clear_signature'] ?? false)) {
            return [
                'global_signature_html' => null,
                'global_signature_text' => null,
            ];
        }

        return [
            'global_signature_html' => $validated['global_signature_html'] ?? $currentMailSettings['global_signature_html'] ?? null,
            'global_signature_text' => $validated['global_signature_text'] ?? $currentMailSettings['global_signature_text'] ?? null,
        ];
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
            'mailbox_password' => ['Un mot de passe est requis pour la boîte mail OVH MX Plan.'],
        ]);
    }

    public function mailbox(): ?MailboxAccount
    {
        return MailboxAccount::query()
            ->where('provider', $this->smtpProviderService->mailboxProvider())
            ->first();
    }
}

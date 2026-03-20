<?php

namespace App\Services\Mailing;

use App\Models\MailboxAccount;
use App\Models\SmtpProviderAccount;
use App\Services\SettingsStore;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SmtpProviderService
{
    private ?bool $providerStorageTableReady = null;

    public function __construct(
        private readonly SettingsStore $settingsStore,
    ) {}

    public function mailboxProvider(): string
    {
        return (string) config('mailing.mailbox_provider', 'ovh_mx_plan');
    }

    public function activeProvider(): string
    {
        $settings = $this->settingsStore->get('mail', config('mailing.defaults.mail', []));
        $provider = (string) ($settings['active_provider'] ?? config('mailing.defaults.mail.active_provider', $this->mailboxProvider()));

        return $this->isKnownProvider($provider)
            ? $provider
            : (string) config('mailing.defaults.mail.active_provider', $this->mailboxProvider());
    }

    public function label(string $provider): string
    {
        return (string) (config("mailing.outbound_providers.{$provider}.label") ?? strtoupper($provider));
    }

    public function supportsImap(string $provider): bool
    {
        return (bool) config("mailing.outbound_providers.{$provider}.supports_imap", false);
    }

    public function providersPayload(?MailboxAccount $mailbox = null): array
    {
        $payload = [];

        foreach ($this->providerKeys() as $provider) {
            $payload[$provider] = $this->providerPayload($provider, $mailbox);
        }

        return $payload;
    }

    public function providerPayload(string $provider, ?MailboxAccount $mailbox = null): array
    {
        $definition = $this->definition($provider);
        $globalSendEnabled = (bool) ($mailbox?->send_enabled ?? false);
        $mailboxEmailConfigured = filled($mailbox?->email);

        if ($provider === $this->mailboxProvider()) {
            $smtpHost = $mailbox?->smtp_host ?? $definition['default_smtp_host'] ?? '';
            $smtpPort = (int) ($mailbox?->smtp_port ?? $definition['default_smtp_port'] ?? 0);
            $smtpSecure = (bool) ($mailbox?->smtp_secure ?? $definition['default_smtp_secure'] ?? false);
            $smtpUsername = (string) ($mailbox?->username ?? '');
            $passwordConfigured = $mailbox !== null && $mailbox->getRawOriginal('password_encrypted') !== null;
            $configured = filled($smtpHost) && filled($smtpUsername) && $passwordConfigured;
            $activatable = $configured && $mailboxEmailConfigured;

            return [
                'provider' => $provider,
                'label' => (string) $definition['label'],
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_secure' => $smtpSecure,
                'smtp_username' => $smtpUsername,
                'smtp_password_configured' => $passwordConfigured,
                'send_enabled' => (bool) ($mailbox?->send_enabled ?? true),
                'supports_imap' => true,
                'supports_sync' => true,
                'configured' => $configured,
                'activatable' => $activatable,
                'ready' => $activatable && $globalSendEnabled,
                'health_status' => $mailbox?->health_status ?? 'unknown',
                'health_message' => $mailbox?->health_message,
                'uses_mailbox_credentials' => true,
            ];
        }

        if (! $this->providerStorageReady()) {
            return [
                'provider' => $provider,
                'label' => (string) $definition['label'],
                'smtp_host' => (string) ($definition['default_smtp_host'] ?? ''),
                'smtp_port' => (int) ($definition['default_smtp_port'] ?? 0),
                'smtp_secure' => (bool) ($definition['default_smtp_secure'] ?? false),
                'smtp_username' => '',
                'smtp_password_configured' => false,
                'send_enabled' => true,
                'supports_imap' => (bool) ($definition['supports_imap'] ?? false),
                'supports_sync' => (bool) ($definition['supports_sync'] ?? false),
                'configured' => false,
                'activatable' => false,
                'ready' => false,
                'health_status' => 'warning',
                'health_message' => $this->providerStorageMessage($provider),
                'uses_mailbox_credentials' => false,
            ];
        }

        $account = $this->account($provider);
        $smtpHost = (string) ($account?->smtp_host ?? $definition['default_smtp_host'] ?? '');
        $smtpPort = (int) ($account?->smtp_port ?? $definition['default_smtp_port'] ?? 0);
        $smtpSecure = (bool) ($account?->smtp_secure ?? $definition['default_smtp_secure'] ?? false);
        $smtpUsername = (string) ($account?->username ?? '');
        $passwordConfigured = $account !== null && $account->getRawOriginal('password_encrypted') !== null;
        $configured = filled($smtpHost) && filled($smtpUsername) && $passwordConfigured;
        $providerSendEnabled = (bool) ($account?->send_enabled ?? true);
        $activatable = $configured && $providerSendEnabled && $mailboxEmailConfigured;

        return [
            'provider' => $provider,
            'label' => (string) $definition['label'],
            'smtp_host' => $smtpHost,
            'smtp_port' => $smtpPort,
            'smtp_secure' => $smtpSecure,
            'smtp_username' => $smtpUsername,
            'smtp_password_configured' => $passwordConfigured,
            'send_enabled' => $providerSendEnabled,
            'supports_imap' => (bool) ($definition['supports_imap'] ?? false),
            'supports_sync' => (bool) ($definition['supports_sync'] ?? false),
            'configured' => $configured,
            'activatable' => $activatable,
            'ready' => $activatable && $globalSendEnabled,
            'health_status' => $account?->health_status ?? 'unknown',
            'health_message' => $account?->health_message,
            'uses_mailbox_credentials' => false,
        ];
    }

    public function runtimeConfiguration(string $provider, MailboxAccount $mailbox): array
    {
        $this->ensureProviderStorageReady($provider, 'active_provider');

        $snapshot = $this->providerPayload($provider, $mailbox);

        if (! $snapshot['configured']) {
            throw ValidationException::withMessages([
                'active_provider' => ["Le provider {$snapshot['label']} n’est pas entièrement configuré pour l’envoi SMTP."],
            ]);
        }

        if (! $snapshot['ready']) {
            throw ValidationException::withMessages([
                'active_provider' => ["Le provider {$snapshot['label']} n’est pas prêt pour l’envoi SMTP."],
            ]);
        }

        if ($provider === $this->mailboxProvider()) {
            return [
                'provider' => $provider,
                'label' => $snapshot['label'],
                'smtp_host' => $snapshot['smtp_host'],
                'smtp_port' => $snapshot['smtp_port'],
                'smtp_secure' => $snapshot['smtp_secure'],
                'smtp_username' => $mailbox->username,
                'smtp_password' => $mailbox->password_encrypted,
            ];
        }

        $account = $this->account($provider);

        if ($account === null) {
            throw ValidationException::withMessages([
                'active_provider' => ["Le provider {$snapshot['label']} n’est pas configuré."],
            ]);
        }

        return [
            'provider' => $provider,
            'label' => $snapshot['label'],
            'smtp_host' => $account->smtp_host,
            'smtp_port' => $account->smtp_port,
            'smtp_secure' => $account->smtp_secure,
            'smtp_username' => $account->username,
            'smtp_password' => $account->password_encrypted,
        ];
    }

    public function validateActiveProvider(string $provider, ?MailboxAccount $mailbox = null): void
    {
        if (! $this->isKnownProvider($provider)) {
            throw ValidationException::withMessages([
                'active_provider' => ['Le provider SMTP actif sélectionné est invalide.'],
            ]);
        }

        $this->ensureProviderStorageReady($provider, 'active_provider');

        $snapshot = $this->providerPayload($provider, $mailbox);

        if (! ($snapshot['activatable'] ?? false)) {
            throw ValidationException::withMessages([
                'active_provider' => ["Impossible d’activer {$snapshot['label']} tant que sa configuration SMTP n’est pas complète et activée."],
            ]);
        }
    }

    public function upsert(array $validated, ?int $updatedBy = null): ?SmtpProviderAccount
    {
        $provider = 'smtp2go';
        $input = Arr::get($validated, "providers.{$provider}", []);
        $existing = $this->account($provider);
        $password = $this->resolvePassword($input, $existing);
        $sendEnabled = (bool) ($input['send_enabled'] ?? $existing?->send_enabled ?? true);
        $hasAnyValue = collect([
            $input['smtp_host'] ?? null,
            $input['smtp_port'] ?? null,
            $input['smtp_username'] ?? null,
            $input['smtp_password'] ?? null,
        ])->contains(fn ($value) => $value !== null && $value !== '');

        if (! $hasAnyValue && $existing === null && $sendEnabled) {
            return null;
        }

        $this->ensureProviderStorageReady($provider, "providers.{$provider}");

        return SmtpProviderAccount::query()->updateOrCreate(
            ['provider' => $provider],
            [
                'user_id' => $updatedBy,
                'provider' => $provider,
                'username' => $input['smtp_username'] ?? $existing?->username,
                'password_encrypted' => $password,
                'smtp_host' => $input['smtp_host'] ?? $existing?->smtp_host ?? $this->definition($provider)['default_smtp_host'],
                'smtp_port' => $input['smtp_port'] ?? $existing?->smtp_port ?? $this->definition($provider)['default_smtp_port'],
                'smtp_secure' => $input['smtp_secure'] ?? $existing?->smtp_secure ?? $this->definition($provider)['default_smtp_secure'],
                'send_enabled' => $sendEnabled,
                'health_status' => $existing?->health_status ?? 'unknown',
                'health_message' => $existing?->health_message,
                'last_tested_at' => $existing?->last_tested_at,
            ],
        );
    }

    public function updateHealth(string $provider, bool $healthy, string $message): void
    {
        if ($provider === $this->mailboxProvider()) {
            return;
        }

        $account = $this->account($provider);

        if ($account === null) {
            return;
        }

        $account->forceFill([
            'health_status' => $healthy ? 'healthy' : 'warning',
            'health_message' => $message,
            'last_tested_at' => now(),
        ])->save();
    }

    public function markTested(string $provider): void
    {
        $account = $this->account($provider);

        if ($account === null) {
            return;
        }

        $account->forceFill([
            'last_tested_at' => now(),
        ])->save();
    }

    public function account(string $provider): ?SmtpProviderAccount
    {
        if ($provider === $this->mailboxProvider() || ! $this->providerStorageReady()) {
            return null;
        }

        return SmtpProviderAccount::query()
            ->where('provider', $provider)
            ->first();
    }

    private function resolvePassword(array $input, ?SmtpProviderAccount $existing): ?string
    {
        $provided = $input['smtp_password'] ?? null;

        if ($provided !== null && $provided !== '') {
            return $provided;
        }

        return $existing?->password_encrypted;
    }

    public function definition(string $provider): array
    {
        return config("mailing.outbound_providers.{$provider}", [
            'label' => strtoupper($provider),
            'supports_imap' => false,
            'supports_sync' => false,
            'uses_mailbox_credentials' => false,
            'default_smtp_host' => '',
            'default_smtp_port' => 587,
            'default_smtp_secure' => false,
        ]);
    }

    private function isKnownProvider(string $provider): bool
    {
        return in_array($provider, $this->providerKeys(), true);
    }

    private function ensureProviderStorageReady(string $provider, string $field): void
    {
        if ($provider === $this->mailboxProvider() || $this->providerStorageReady()) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [$this->providerStorageMessage($provider)],
        ]);
    }

    private function providerStorageReady(): bool
    {
        return $this->providerStorageTableReady ??= Schema::hasTable('smtp_provider_accounts');
    }

    private function providerStorageMessage(string $provider): string
    {
        return "Le schéma {$this->label($provider)} n’est pas prêt. Exécutez php artisan migrate avant de configurer ou d’activer ce provider.";
    }

    private function providerKeys(): array
    {
        return array_keys(config('mailing.outbound_providers', []));
    }
}

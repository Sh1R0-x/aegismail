<?php

namespace App\Services\Mailing;

use App\Services\Mailing\Contracts\MailGatewayClient;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Throwable;

class MailboxConnectionTester
{
    public function __construct(
        private readonly MailGatewayClient $gatewayClient,
        private readonly MailboxSettingsService $mailboxSettingsService,
        private readonly MailEventLogger $eventLogger,
        private readonly SmtpProviderService $smtpProviderService,
    ) {}

    public function testImap(array $overrides = []): array
    {
        return $this->testProtocol('imap', $overrides);
    }

    public function testSmtp(array $overrides = []): array
    {
        return $this->testProtocol('smtp', $overrides);
    }

    private function testProtocol(string $protocol, array $overrides): array
    {
        $provider = (string) ($overrides['provider'] ?? $this->smtpProviderService->activeProvider());
        $mailboxConfiguration = $this->resolveConfiguration($protocol, $provider, $overrides);
        $sanitizedPayload = Arr::except($mailboxConfiguration, ['password']);

        try {
            $result = $protocol === 'imap'
                ? $this->gatewayClient->testImap($mailboxConfiguration)
                : $this->gatewayClient->testSmtp($mailboxConfiguration);
        } catch (Throwable $throwable) {
            report($throwable);

            $mailbox = $protocol === 'imap' || $provider === $this->smtpProviderService->mailboxProvider()
                ? $this->mailboxSettingsService->updateHealth(false, "Passerelle mail indisponible pendant le test {$protocol}.")
                : $this->mailboxSettingsService->mailbox();

            if ($protocol === 'smtp') {
                $this->smtpProviderService->updateHealth($provider, false, "Passerelle mail indisponible pendant le test {$protocol}.");
            }

            $this->eventLogger->log(
                "mailbox.test_{$protocol}_failed",
                array_merge($sanitizedPayload, [
                    'provider' => $provider,
                    'driver' => config('mailing.gateway.driver'),
                    'error' => $throwable->getMessage(),
                ]),
                ['mailbox_account_id' => $mailbox?->id],
            );

            return [
                'success' => false,
                'protocol' => $protocol,
                'provider' => $provider,
                'provider_label' => $this->smtpProviderService->label($provider),
                'driver' => config('mailing.gateway.driver'),
                'message' => "Le service de test {$protocol} est indisponible pour le moment.",
                'status_code' => 502,
            ];
        }

        $operatorMessage = $this->operatorMessage($protocol, $provider, $result);
        $statusCode = ($result['success'] ?? false) ? 200 : 422;
        $mailbox = $protocol === 'imap' || $provider === $this->smtpProviderService->mailboxProvider()
            ? $this->mailboxSettingsService->updateHealth((bool) ($result['success'] ?? false), $operatorMessage)
            : $this->mailboxSettingsService->mailbox();

        if ($protocol === 'smtp') {
            $this->smtpProviderService->updateHealth($provider, (bool) ($result['success'] ?? false), $operatorMessage);
        }

        $this->eventLogger->log(
            "mailbox.test_{$protocol}_".(($result['success'] ?? false) ? 'succeeded' : 'failed'),
            array_merge($sanitizedPayload, Arr::except($result, ['password']), [
                'provider' => $provider,
                'operator_message' => $operatorMessage,
            ]),
            ['mailbox_account_id' => $mailbox?->id],
        );

        return array_merge($result, [
            'message' => $operatorMessage,
            'protocol' => $protocol,
            'provider' => $provider,
            'provider_label' => $this->smtpProviderService->label($provider),
            'status_code' => $statusCode,
        ]);
    }

    private function resolveConfiguration(string $protocol, string $provider, array $overrides): array
    {
        if ($protocol === 'imap') {
            if (! $this->smtpProviderService->supportsImap($provider)) {
                throw ValidationException::withMessages([
                    'provider' => ["Le provider {$this->smtpProviderService->label($provider)} ne supporte pas l’IMAP dans AEGIS MAILING."],
                ]);
            }

            $resolved = array_replace($this->mailboxSettingsService->getConnectionConfiguration(), Arr::except($overrides, [
                'provider',
                'smtp_username',
                'smtp_password',
            ]));

            $requiredKeys = [
                'sender_email',
                'mailbox_username',
                'mailbox_password',
                'imap_host',
                'imap_port',
                'imap_secure',
            ];

            $missing = [];

            foreach ($requiredKeys as $key) {
                if (! array_key_exists($key, $resolved) || $resolved[$key] === null || $resolved[$key] === '') {
                    $missing[$key] = [$this->missingFieldMessage($key, $protocol, $provider)];
                }
            }

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }

            return [
                'provider' => $provider,
                'email' => $resolved['sender_email'],
                'username' => $resolved['mailbox_username'],
                'password' => $resolved['mailbox_password'],
                'imap_host' => $resolved['imap_host'],
                'imap_port' => $resolved['imap_port'],
                'imap_secure' => $resolved['imap_secure'],
            ];
        }

        $definition = $this->smtpProviderService->definition($provider);
        $mailbox = $this->mailboxSettingsService->mailbox();
        $account = $this->smtpProviderService->account($provider);
        $resolved = [
            'sender_email' => $overrides['sender_email'] ?? $mailbox?->email ?? '',
            'smtp_host' => $overrides['smtp_host'] ?? ($provider === $this->smtpProviderService->mailboxProvider()
                ? ($mailbox?->smtp_host ?? $definition['default_smtp_host'] ?? '')
                : ($account?->smtp_host ?? $definition['default_smtp_host'] ?? '')),
            'smtp_port' => $overrides['smtp_port'] ?? ($provider === $this->smtpProviderService->mailboxProvider()
                ? ($mailbox?->smtp_port ?? $definition['default_smtp_port'] ?? null)
                : ($account?->smtp_port ?? $definition['default_smtp_port'] ?? null)),
            'smtp_secure' => $overrides['smtp_secure'] ?? ($provider === $this->smtpProviderService->mailboxProvider()
                ? ($mailbox?->smtp_secure ?? $definition['default_smtp_secure'] ?? false)
                : ($account?->smtp_secure ?? $definition['default_smtp_secure'] ?? false)),
            'smtp_username' => $overrides['smtp_username']
                ?? ($provider === $this->smtpProviderService->mailboxProvider()
                    ? ($overrides['mailbox_username'] ?? $mailbox?->username ?? '')
                    : ($account?->username ?? '')),
            'smtp_password' => $overrides['smtp_password']
                ?? ($provider === $this->smtpProviderService->mailboxProvider()
                    ? ($overrides['mailbox_password'] ?? $mailbox?->password_encrypted)
                    : ($account?->password_encrypted)),
        ];

        $requiredKeys = [
            'sender_email',
            'smtp_username',
            'smtp_password',
            'smtp_host',
            'smtp_port',
            'smtp_secure',
        ];

        $missing = [];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $resolved) || $resolved[$key] === null || $resolved[$key] === '') {
                $missing[$key] = [$this->missingFieldMessage($key, $protocol, $provider)];
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        return [
            'provider' => $provider,
            'email' => $resolved['sender_email'],
            'username' => $resolved['smtp_username'],
            'password' => $resolved['smtp_password'],
            'smtp_host' => $resolved['smtp_host'],
            'smtp_port' => $resolved['smtp_port'],
            'smtp_secure' => $resolved['smtp_secure'],
        ];
    }

    private function missingFieldMessage(string $key, string $protocol, string $provider): string
    {
        $protocolLabel = strtoupper($protocol);
        $providerLabel = $this->smtpProviderService->label($provider);
        $messages = [
            'sender_email' => "L’adresse d’envoi est requise pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'mailbox_username' => "L’identifiant de la boîte mail est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'mailbox_password' => "Le mot de passe de la boîte mail est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'smtp_username' => "L’identifiant SMTP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'smtp_password' => "Le mot de passe SMTP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'imap_host' => "L’hôte IMAP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'imap_port' => "Le port IMAP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'imap_secure' => "Le mode de sécurité IMAP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'smtp_host' => "L’hôte SMTP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'smtp_port' => "Le port SMTP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
            'smtp_secure' => "Le mode de sécurité SMTP est requis pour tester la connexion {$protocolLabel} {$providerLabel}.",
        ];

        return $messages[$key] ?? "Le champ {$key} est requis pour tester la connexion {$protocolLabel}.";
    }

    private function operatorMessage(string $protocol, string $provider, array $result): string
    {
        $protocolLabel = strtoupper($protocol);
        $providerLabel = $this->smtpProviderService->label($provider);

        if (($result['success'] ?? false) === true) {
            return "Test {$protocolLabel} réussi. La connexion {$providerLabel} a bien été établie.";
        }

        $rawMessage = strtolower(trim((string) ($result['message'] ?? '')));

        return match (true) {
            $this->containsAny($rawMessage, ['auth', 'authentication', 'invalid credentials', 'login failed', 'bad credentials', 'username', 'password']) => "La connexion {$protocolLabel} a échoué : l’identifiant ou le mot de passe semble incorrect.",
            $this->containsAny($rawMessage, ['timeout', 'timed out']) => "La connexion {$protocolLabel} a expiré avant la réponse du serveur.",
            $this->containsAny($rawMessage, ['connection refused', 'refused', 'network is unreachable']) => "La connexion {$protocolLabel} a été refusée par le serveur distant.",
            $this->containsAny($rawMessage, ['tls', 'ssl', 'certificate', 'handshake', 'starttls']) => "La connexion {$protocolLabel} a échoué : le paramètre de sécurité TLS/SSL semble incohérent.",
            $this->containsAny($rawMessage, ['host', 'port', 'dns', 'resolve', 'configured host', 'getaddrinfo', 'hôte rejeté']) => "La connexion {$protocolLabel} a échoué : l’hôte ou le port semble incorrect.",
            default => "La connexion {$protocolLabel} a échoué. Vérifiez l’hôte, le port, le mode de sécurité et les identifiants.",
        };
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

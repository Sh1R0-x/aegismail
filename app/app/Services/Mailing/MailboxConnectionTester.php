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
    ) {
    }

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
        $mailboxConfiguration = $this->resolveConfiguration($protocol, $overrides);
        $sanitizedPayload = Arr::except($mailboxConfiguration, ['password']);

        try {
            $result = $protocol === 'imap'
                ? $this->gatewayClient->testImap($mailboxConfiguration)
                : $this->gatewayClient->testSmtp($mailboxConfiguration);
        } catch (Throwable $throwable) {
            report($throwable);

            $mailbox = $this->mailboxSettingsService->updateHealth(false, "Mail gateway unavailable during {$protocol} test.");

            $this->eventLogger->log(
                "mailbox.test_{$protocol}_failed",
                array_merge($sanitizedPayload, [
                    'driver' => config('mailing.gateway.driver'),
                    'error' => $throwable->getMessage(),
                ]),
                ['mailbox_account_id' => $mailbox?->id],
            );

            return [
                'success' => false,
                'protocol' => $protocol,
                'driver' => config('mailing.gateway.driver'),
                'message' => "Le service de test {$protocol} est indisponible pour le moment.",
                'status_code' => 502,
            ];
        }

        $operatorMessage = $this->operatorMessage($protocol, $result);
        $statusCode = ($result['success'] ?? false) ? 200 : 422;
        $mailbox = $this->mailboxSettingsService->updateHealth((bool) ($result['success'] ?? false), $operatorMessage);

        $this->eventLogger->log(
            "mailbox.test_{$protocol}_".(($result['success'] ?? false) ? 'succeeded' : 'failed'),
            array_merge($sanitizedPayload, Arr::except($result, ['password']), [
                'operator_message' => $operatorMessage,
            ]),
            ['mailbox_account_id' => $mailbox?->id],
        );

        return array_merge($result, [
            'message' => $operatorMessage,
            'protocol' => $protocol,
            'provider' => config('mailing.provider'),
            'status_code' => $statusCode,
        ]);
    }

    private function resolveConfiguration(string $protocol, array $overrides): array
    {
        $resolved = array_replace($this->mailboxSettingsService->getConnectionConfiguration(), $overrides);

        $requiredKeys = [
            'sender_email',
            'mailbox_username',
            'mailbox_password',
            "{$protocol}_host",
            "{$protocol}_port",
            "{$protocol}_secure",
        ];

        $missing = [];

        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $resolved) || $resolved[$key] === null || $resolved[$key] === '') {
                $missing[$key] = [$this->missingFieldMessage($key, $protocol)];
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        return [
            'provider' => config('mailing.provider'),
            'email' => $resolved['sender_email'],
            'username' => $resolved['mailbox_username'],
            'password' => $resolved['mailbox_password'],
            "{$protocol}_host" => $resolved["{$protocol}_host"],
            "{$protocol}_port" => $resolved["{$protocol}_port"],
            "{$protocol}_secure" => $resolved["{$protocol}_secure"],
        ];
    }

    private function missingFieldMessage(string $key, string $protocol): string
    {
        $protocolLabel = strtoupper($protocol);
        $messages = [
            'sender_email' => "L’adresse d’envoi est requise pour tester la connexion {$protocolLabel}.",
            'mailbox_username' => "L’identifiant de la boîte mail est requis pour tester la connexion {$protocolLabel}.",
            'mailbox_password' => "Le mot de passe de la boîte mail est requis pour tester la connexion {$protocolLabel}.",
            'imap_host' => "L’hôte IMAP est requis pour tester la connexion {$protocolLabel}.",
            'imap_port' => "Le port IMAP est requis pour tester la connexion {$protocolLabel}.",
            'imap_secure' => "Le mode de sécurité IMAP est requis pour tester la connexion {$protocolLabel}.",
            'smtp_host' => "L’hôte SMTP est requis pour tester la connexion {$protocolLabel}.",
            'smtp_port' => "Le port SMTP est requis pour tester la connexion {$protocolLabel}.",
            'smtp_secure' => "Le mode de sécurité SMTP est requis pour tester la connexion {$protocolLabel}.",
        ];

        return $messages[$key] ?? "Le champ {$key} est requis pour tester la connexion {$protocolLabel}.";
    }

    private function operatorMessage(string $protocol, array $result): string
    {
        $protocolLabel = strtoupper($protocol);

        if (($result['success'] ?? false) === true) {
            return "Test {$protocolLabel} réussi. La connexion à la boîte OVH MX Plan a bien été établie.";
        }

        $rawMessage = strtolower(trim((string) ($result['message'] ?? '')));

        return match (true) {
            $this->containsAny($rawMessage, ['auth', 'authentication', 'invalid credentials', 'login failed', 'bad credentials', 'username', 'password']) =>
                "La connexion {$protocolLabel} a échoué : l’identifiant ou le mot de passe semble incorrect.",
            $this->containsAny($rawMessage, ['timeout', 'timed out']) =>
                "La connexion {$protocolLabel} a expiré avant la réponse du serveur.",
            $this->containsAny($rawMessage, ['connection refused', 'refused', 'network is unreachable']) =>
                "La connexion {$protocolLabel} a été refusée par le serveur distant.",
            $this->containsAny($rawMessage, ['tls', 'ssl', 'certificate', 'handshake', 'starttls']) =>
                "La connexion {$protocolLabel} a échoué : le paramètre de sécurité TLS/SSL semble incohérent.",
            $this->containsAny($rawMessage, ['host', 'port', 'dns', 'resolve', 'configured host', 'getaddrinfo']) =>
                "La connexion {$protocolLabel} a échoué : l’hôte ou le port semble incorrect.",
            default =>
                "La connexion {$protocolLabel} a échoué. Vérifiez l’hôte, le port, le mode de sécurité et les identifiants.",
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

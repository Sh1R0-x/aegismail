<?php

namespace App\Services\Mailing\Gateway;

use App\Services\Mailing\Contracts\MailGatewayClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StubMailGatewayClient implements MailGatewayClient
{
    public function testImap(array $configuration): array
    {
        return $this->probe('imap', $configuration);
    }

    public function testSmtp(array $configuration): array
    {
        return $this->probe('smtp', $configuration);
    }

    public function dispatchMessage(array $payload): array
    {
        return [
            'success' => true,
            'driver' => 'stub',
            'message' => 'Outbound message accepted by the stub gateway.',
            'accepted_at' => Carbon::now()->toIso8601String(),
            'mail_message_id' => $payload['mail_message_id'] ?? null,
        ];
    }

    public function syncMailbox(array $payload): array
    {
        return [
            'success' => true,
            'driver' => 'stub',
            'message' => 'Mailbox sync accepted by the stub gateway.',
            'accepted_at' => Carbon::now()->toIso8601String(),
            'mailbox_account_id' => $payload['mailbox_account_id'] ?? null,
        ];
    }

    private function probe(string $protocol, array $configuration): array
    {
        $host = $configuration["{$protocol}_host"] ?? null;
        $port = $configuration["{$protocol}_port"] ?? null;
        $username = $configuration['username'] ?? null;
        $password = $configuration['password'] ?? null;

        if ($host === null || $host === '' || $port === null || $username === null || $username === '' || $password === null || $password === '') {
            return [
                'success' => false,
                'driver' => 'stub',
                'protocol' => $protocol,
                'message' => "Incomplete {$protocol} configuration.",
                'tested_at' => Carbon::now()->toIso8601String(),
            ];
        }

        if (Str::contains(Str::lower((string) $host), ['invalid', 'fail'])) {
            return [
                'success' => false,
                'driver' => 'stub',
                'protocol' => $protocol,
                'message' => "Stub {$protocol} test rejected the configured host.",
                'tested_at' => Carbon::now()->toIso8601String(),
            ];
        }

        return [
            'success' => true,
            'driver' => 'stub',
            'protocol' => $protocol,
            'message' => "Stub {$protocol} test succeeded.",
            'tested_at' => Carbon::now()->toIso8601String(),
        ];
    }
}

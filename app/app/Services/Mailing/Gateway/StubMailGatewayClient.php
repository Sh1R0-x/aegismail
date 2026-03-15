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
        $toEmails = $payload['to_emails'] ?? [];
        $shouldFail = collect($toEmails)
            ->contains(fn ($email) => Str::contains(Str::lower((string) $email), ['fail', 'bounce']));

        if ($shouldFail) {
            return [
                'success' => false,
                'driver' => 'stub',
                'message' => 'Outbound message rejected by the stub gateway.',
                'accepted_at' => Carbon::now()->toIso8601String(),
                'mail_message_id' => $payload['mail_message_id'] ?? null,
                'message_id_header' => $payload['message_id_header'] ?? null,
            ];
        }

        return [
            'success' => true,
            'driver' => 'stub',
            'message' => 'Outbound message accepted by the stub gateway.',
            'accepted_at' => Carbon::now()->toIso8601String(),
            'mail_message_id' => $payload['mail_message_id'] ?? null,
            'message_id_header' => $payload['message_id_header'] ?? null,
            'headers_json' => $payload['headers_json'] ?? [],
        ];
    }

    public function syncMailbox(array $payload): array
    {
        $messages = collect($payload['stub_messages'] ?? [])
            ->filter(fn ($message) => is_array($message))
            ->sortBy(fn (array $message) => (int) ($message['uid'] ?? 0))
            ->values()
            ->all();

        return [
            'success' => true,
            'driver' => 'stub',
            'message' => 'Mailbox sync accepted by the stub gateway.',
            'accepted_at' => Carbon::now()->toIso8601String(),
            'mailbox_account_id' => $payload['mailbox_account_id'] ?? null,
            'folder' => $payload['folder'] ?? 'INBOX',
            'from_uid' => $payload['from_uid'] ?? 0,
            'highest_uid' => collect($messages)->max('uid') ?: ($payload['from_uid'] ?? 0),
            'messages' => $messages,
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

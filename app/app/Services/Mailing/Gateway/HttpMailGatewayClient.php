<?php

namespace App\Services\Mailing\Gateway;

use App\Services\Mailing\Contracts\MailGatewayClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class HttpMailGatewayClient implements MailGatewayClient
{
    public function testImap(array $configuration): array
    {
        return $this->post('/v1/tests/imap', $configuration);
    }

    public function testSmtp(array $configuration): array
    {
        return $this->post('/v1/tests/smtp', $configuration);
    }

    public function dispatchMessage(array $payload): array
    {
        return $this->post('/v1/messages/send', $payload);
    }

    public function syncMailbox(array $payload): array
    {
        return $this->post('/v1/mailboxes/sync', $payload);
    }

    private function client(): PendingRequest
    {
        $client = Http::acceptJson()
            ->baseUrl(config('services.mail_gateway.base_url'))
            ->timeout(config('services.mail_gateway.timeout', 10));

        if ($sharedSecret = config('services.mail_gateway.shared_secret')) {
            $client = $client->withHeaders([
                'X-Aegis-Gateway-Secret' => $sharedSecret,
            ]);
        }

        return $client;
    }

    private function post(string $path, array $payload): array
    {
        $response = $this->client()->post($path, $payload);

        $body = $response->json();

        if (is_array($body)) {
            return array_replace([
                'success' => $response->successful(),
                'driver' => 'http',
            ], $body);
        }

        return [
            'success' => $response->successful(),
            'driver' => 'http',
            'message' => $response->body(),
        ];
    }
}

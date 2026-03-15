<?php

namespace App\Jobs\Mailing;

use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\MailEventLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use RuntimeException;

class DispatchMailMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $payload)
    {
        $this->onQueue(config('mailing.queues.outbound'));
    }

    public function handle(MailGatewayClient $gatewayClient, MailEventLogger $eventLogger): void
    {
        $result = $gatewayClient->dispatchMessage($this->payload);

        $eventLogger->log(
            'mail_message.dispatch_requested',
            [
                'driver' => $result['driver'] ?? config('mailing.gateway.driver'),
                'result' => Arr::except($result, ['html_body', 'text_body']),
                'payload' => Arr::except($this->payload, ['html_body', 'text_body', 'headers_json', 'password', 'mailbox_password']),
            ],
            [
                'mailbox_account_id' => $this->payload['mailbox_account_id'] ?? null,
                'campaign_id' => $this->payload['campaign_id'] ?? null,
                'recipient_id' => $this->payload['recipient_id'] ?? null,
                'thread_id' => $this->payload['thread_id'] ?? null,
                'message_id' => $this->payload['mail_message_id'] ?? null,
            ],
            $this->payload['idempotency_key'] ?? null,
        );

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Mail gateway rejected outbound dispatch.');
        }
    }
}

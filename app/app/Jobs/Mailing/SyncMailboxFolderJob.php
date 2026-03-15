<?php

namespace App\Jobs\Mailing;

use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\MailEventLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class SyncMailboxFolderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $payload)
    {
        $this->onQueue(config('mailing.queues.sync'));
    }

    public function handle(MailGatewayClient $gatewayClient, MailEventLogger $eventLogger): void
    {
        $result = $gatewayClient->syncMailbox($this->payload);

        $eventLogger->log(
            'mailbox.sync_requested',
            $result,
            [
                'mailbox_account_id' => $this->payload['mailbox_account_id'] ?? null,
            ],
            $this->payload['idempotency_key'] ?? null,
        );

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException($result['message'] ?? 'Mail gateway rejected mailbox sync.');
        }
    }
}

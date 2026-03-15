<?php

namespace App\Jobs\Mailing;

use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\Inbound\MailboxSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMailboxFolderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $payload)
    {
        $this->onQueue(config('mailing.queues.sync'));
    }

    public function handle(MailGatewayClient $gatewayClient, MailboxSyncService $mailboxSyncService): void
    {
        $mailboxSyncService->sync($this->payload, $gatewayClient);
    }
}

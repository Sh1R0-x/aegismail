<?php

namespace App\Jobs\Mailing;

use App\Services\Mailing\Contracts\MailGatewayClient;
use App\Services\Mailing\Outbound\OutboundMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchMailMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly array $payload)
    {
        $this->onQueue(config('mailing.queues.outbound'));
    }

    public function handle(MailGatewayClient $gatewayClient, OutboundMailService $outboundMailService): void
    {
        $outboundMailService->dispatchQueuedMessage($this->payload, $gatewayClient);
    }
}

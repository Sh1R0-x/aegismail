<?php

namespace App\Services\Mailing;

use App\Models\MailEvent;
use Illuminate\Support\Carbon;

class MailEventLogger
{
    public function log(
        string $eventType,
        array $payload = [],
        array $relations = [],
        ?string $idempotencyKey = null,
    ): MailEvent {
        $attributes = [
            'mailbox_account_id' => $relations['mailbox_account_id'] ?? null,
            'campaign_id' => $relations['campaign_id'] ?? null,
            'recipient_id' => $relations['recipient_id'] ?? null,
            'thread_id' => $relations['thread_id'] ?? null,
            'message_id' => $relations['message_id'] ?? null,
            'event_type' => $eventType,
            'event_payload' => $payload,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ];

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $event = MailEvent::query()->firstOrNew(['idempotency_key' => $idempotencyKey]);

            if (! $event->exists) {
                $event->fill($attributes)->save();
            }

            return $event;
        }

        return MailEvent::query()->create($attributes);
    }
}

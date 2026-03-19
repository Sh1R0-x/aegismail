<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'mailbox_account_id',
        'campaign_id',
        'recipient_id',
        'thread_id',
        'message_id',
        'event_type',
        'event_payload',
        'idempotency_key',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function mailboxAccount(): BelongsTo
    {
        return $this->belongsTo(MailboxAccount::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailCampaign::class, 'campaign_id')->withTrashed();
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(MailRecipient::class, 'recipient_id');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'thread_id');
    }

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'message_id');
    }
}

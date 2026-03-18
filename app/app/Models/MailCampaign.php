<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox_account_id',
        'user_id',
        'name',
        'mode',
        'draft_id',
        'status',
        'last_edited_at',
        'send_window_json',
        'throttling_json',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'send_window_json' => 'array',
            'throttling_json' => 'array',
            'last_edited_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function mailboxAccount(): BelongsTo
    {
        return $this->belongsTo(MailboxAccount::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MailDraft::class, 'draft_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class, 'campaign_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MailEvent::class, 'campaign_id');
    }
}

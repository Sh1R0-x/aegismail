<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailDraft extends Model
{
    use HasFactory;

    protected $fillable = [
        'mailbox_account_id',
        'outbound_provider',
        'user_id',
        'mode',
        'template_id',
        'subject',
        'html_body',
        'text_body',
        'signature_snapshot',
        'payload_json',
        'status',
        'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'scheduled_at' => 'immutable_datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class, 'template_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MailCampaign::class, 'draft_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class, 'draft_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'mailbox_account_id',
        'recipient_id',
        'direction',
        'provider_folder',
        'provider_uid',
        'message_id_header',
        'in_reply_to_header',
        'references_header',
        'aegis_tracking_id',
        'from_email',
        'to_emails',
        'cc_emails',
        'bcc_emails',
        'subject',
        'html_body',
        'text_body',
        'headers_json',
        'classification',
        'sent_at',
        'received_at',
        'opened_first_at',
        'clicked_first_at',
    ];

    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            'bcc_emails' => 'array',
            'headers_json' => 'array',
            'sent_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
            'opened_first_at' => 'immutable_datetime',
            'clicked_first_at' => 'immutable_datetime',
        ];
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MailThread::class, 'thread_id');
    }

    public function mailboxAccount(): BelongsTo
    {
        return $this->belongsTo(MailboxAccount::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(MailRecipient::class, 'recipient_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MailEvent::class, 'message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MailAttachment::class, 'message_id');
    }
}

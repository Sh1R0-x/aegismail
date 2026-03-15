<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_uuid',
        'mailbox_account_id',
        'organization_id',
        'contact_id',
        'subject_canonical',
        'first_message_at',
        'last_message_at',
        'last_direction',
        'reply_received',
        'auto_reply_received',
        'confidence_score',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'first_message_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'reply_received' => 'boolean',
            'auto_reply_received' => 'boolean',
            'confidence_score' => 'decimal:2',
        ];
    }

    public function mailboxAccount(): BelongsTo
    {
        return $this->belongsTo(MailboxAccount::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'thread_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MailEvent::class, 'thread_id');
    }
}

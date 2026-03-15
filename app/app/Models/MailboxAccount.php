<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailboxAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'email',
        'display_name',
        'username',
        'password_encrypted',
        'imap_host',
        'imap_port',
        'imap_secure',
        'smtp_host',
        'smtp_port',
        'smtp_secure',
        'sync_enabled',
        'send_enabled',
        'last_inbox_uid',
        'last_sent_uid',
        'last_sync_at',
        'health_status',
        'health_message',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'password_encrypted' => 'encrypted',
            'imap_secure' => 'boolean',
            'smtp_secure' => 'boolean',
            'sync_enabled' => 'boolean',
            'send_enabled' => 'boolean',
            'last_sync_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function drafts(): HasMany
    {
        return $this->hasMany(MailDraft::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MailCampaign::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(MailThread::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MailEvent::class);
    }
}

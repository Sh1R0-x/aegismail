<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailRecipient extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'organization_id',
        'contact_id',
        'contact_email_id',
        'email',
        'status',
        'last_event_at',
        'scheduled_for',
        'sent_at',
        'replied_at',
        'auto_replied_at',
        'bounced_at',
        'unsubscribe_at',
        'score_bucket',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'immutable_datetime',
            'scheduled_for' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'replied_at' => 'immutable_datetime',
            'auto_replied_at' => 'immutable_datetime',
            'bounced_at' => 'immutable_datetime',
            'unsubscribe_at' => 'immutable_datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MailCampaign::class, 'campaign_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactEmail(): BelongsTo
    {
        return $this->belongsTo(ContactEmail::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MailMessage::class, 'recipient_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MailEvent::class, 'recipient_id');
    }
}

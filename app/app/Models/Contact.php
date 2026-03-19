<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'first_name',
        'last_name',
        'full_name',
        'job_title',
        'phone',
        'phone_landline',
        'phone_mobile',
        'linkedin_url',
        'country',
        'city',
        'tags_json',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tags_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function contactEmails(): HasMany
    {
        return $this->hasMany(ContactEmail::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }

    public function threads(): HasMany
    {
        return $this->hasMany(MailThread::class);
    }
}

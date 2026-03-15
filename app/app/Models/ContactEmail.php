<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'email',
        'is_primary',
        'opt_out_at',
        'opt_out_reason',
        'bounce_status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'opt_out_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }
}

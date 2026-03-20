<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmtpProviderAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'username',
        'password_encrypted',
        'smtp_host',
        'smtp_port',
        'smtp_secure',
        'send_enabled',
        'health_status',
        'health_message',
        'last_tested_at',
    ];

    protected $hidden = [
        'password_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'password_encrypted' => 'encrypted',
            'smtp_secure' => 'boolean',
            'send_enabled' => 'boolean',
            'last_tested_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

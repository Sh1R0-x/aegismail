<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'website',
        'notes',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function mailRecipients(): HasMany
    {
        return $this->hasMany(MailRecipient::class);
    }

    public function mailThreads(): HasMany
    {
        return $this->hasMany(MailThread::class);
    }
}

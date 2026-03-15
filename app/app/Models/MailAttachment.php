<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'draft_id',
        'original_name',
        'mime_type',
        'size_bytes',
        'storage_disk',
        'storage_path',
        'content_id',
        'disposition',
    ];

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'message_id');
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(MailDraft::class, 'draft_id');
    }
}

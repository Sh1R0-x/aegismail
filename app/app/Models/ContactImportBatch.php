<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactImportBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_name',
        'source_type',
        'status',
        'imported_contacts_count',
        'skipped_rows_count',
        'invalid_rows_count',
        'contact_ids_json',
        'summary_json',
        'report_json',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'contact_ids_json' => 'array',
            'summary_json' => 'array',
            'report_json' => 'array',
            'processed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

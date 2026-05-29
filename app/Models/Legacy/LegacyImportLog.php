<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyImportLog extends Model
{
    protected $fillable = [
        'batch_id',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'action',
        'status',
        'message',
        'payload',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }
}

<?php

namespace App\Models\Legacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyReference extends Model
{
    protected $fillable = [
        'batch_id',
        'source_system',
        'source_table',
        'source_id',
        'target_table',
        'target_id',
        'fingerprint',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(LegacyImportBatch::class, 'batch_id');
    }
}

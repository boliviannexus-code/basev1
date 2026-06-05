<?php

namespace App\Models\Legacy;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegacyImportBatch extends Model
{
    protected $fillable = [
        'source_system',
        'source_path',
        'source_hash',
        'company_id',
        'mode',
        'status',
        'summary',
        'report_path',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LegacyImportLog::class, 'batch_id');
    }
}

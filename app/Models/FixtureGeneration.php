<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class FixtureGeneration extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'tournament_id',
        'category_id',
        'generated_by',
        'status',
        'config',
        'matches_count',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'generated_at' => 'datetime',
            'matches_count' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DivisionCategory::class, 'category_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class);
    }
}

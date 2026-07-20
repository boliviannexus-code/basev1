<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class StandingAdjustment extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'tournament_id',
        'category_id',
        'team_id',
        'created_by',
        'series',
        'points_adjustment',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'points_adjustment' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DivisionCategory::class, 'category_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

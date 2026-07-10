<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\MatchdayDateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MatchdayDate extends Model implements Auditable
{
    /** @use HasFactory<MatchdayDateFactory> */
    use AuditsCompanyChanges, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'matchday_id',
        'court_id',
        'date',
        'sort_order',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function matchday(): BelongsTo
    {
        return $this->belongsTo(Matchday::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function fixtureMatches(): HasMany
    {
        return $this->hasMany(FixtureMatch::class);
    }

    public function fiscals(): HasMany
    {
        return $this->hasMany(MatchdayDateFiscal::class);
    }
}

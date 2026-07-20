<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\MatchdayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Matchday extends Model implements Auditable
{
    /** @use HasFactory<MatchdayFactory> */
    use AuditsCompanyChanges, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'season_id',
        'number',
        'name',
        'status',
        'scheduled_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'scheduled_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function dates(): HasMany
    {
        return $this->hasMany(MatchdayDate::class);
    }

    public function fixtureMatches(): HasManyThrough
    {
        return $this->hasManyThrough(
            FixtureMatch::class,
            MatchdayDate::class,
            'matchday_id',
            'matchday_date_id'
        );
    }
}

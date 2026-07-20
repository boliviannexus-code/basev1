<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class FixtureMatch extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'fixture_generation_id',
        'tournament_id',
        'division_id',
        'category_id',
        'phase',
        'stage',
        'stage_order',
        'series',
        'round_number',
        'match_number',
        'tie_number',
        'leg_number',
        'home_registration_id',
        'away_registration_id',
        'home_team_id',
        'away_team_id',
        'home_seed',
        'away_seed',
        'matchday_date_id',
        'scheduled_time',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'stage_order' => 'integer',
            'round_number' => 'integer',
            'match_number' => 'integer',
            'tie_number' => 'integer',
            'leg_number' => 'integer',
        ];
    }

    public function fixtureGeneration(): BelongsTo
    {
        return $this->belongsTo(FixtureGeneration::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DivisionCategory::class, 'category_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function homeRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'home_registration_id');
    }

    public function awayRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'away_registration_id');
    }

    public function matchdayDate(): BelongsTo
    {
        return $this->belongsTo(MatchdayDate::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(MatchReport::class);
    }

    public function redCardSanctions(): HasMany
    {
        return $this->hasMany(RedCardSanction::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class MatchReport extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'fixture_match_id',
        'referee_1',
        'referee_2',
        'referee_3',
        'home_brought_ball',
        'away_brought_ball',
        'home_present',
        'away_present',
        'home_paid_court_fee',
        'away_paid_court_fee',
        'control_items',
        'home_score',
        'away_score',
        'home_points',
        'away_points',
        'wo_side',
        'wo_reason',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'home_brought_ball' => 'boolean',
            'away_brought_ball' => 'boolean',
            'home_present' => 'boolean',
            'away_present' => 'boolean',
            'home_paid_court_fee' => 'boolean',
            'away_paid_court_fee' => 'boolean',
            'control_items' => 'array',
            'home_score' => 'integer',
            'away_score' => 'integer',
            'home_points' => 'integer',
            'away_points' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function fixtureMatch(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchReportPlayer::class);
    }
}

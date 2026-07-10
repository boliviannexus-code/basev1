<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MatchReportPlayer extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'match_report_id',
        'tournament_team_player_id',
        'player_id',
        'team_id',
        'team_side',
        'jersey_number',
        'goals',
        'yellow_cards',
        'red_cards',
    ];

    protected function casts(): array
    {
        return [
            'goals' => 'integer',
            'jersey_number' => 'integer',
            'yellow_cards' => 'integer',
            'red_cards' => 'integer',
        ];
    }

    public function matchReport(): BelongsTo
    {
        return $this->belongsTo(MatchReport::class);
    }

    public function tournamentTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(TournamentTeamPlayer::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class RedCardSanction extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'fixture_match_id',
        'tournament_team_player_id',
        'player_id',
        'team_id',
        'red_card_article_id',
        'jersey_number',
        'action_detail',
        'suspended_matches',
    ];

    protected function casts(): array
    {
        return [
            'jersey_number' => 'integer',
            'suspended_matches' => 'integer',
        ];
    }

    public function fixtureMatch(): BelongsTo
    {
        return $this->belongsTo(FixtureMatch::class);
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

    public function article(): BelongsTo
    {
        return $this->belongsTo(RedCardArticle::class, 'red_card_article_id');
    }
}

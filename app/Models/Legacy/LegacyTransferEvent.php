<?php

namespace App\Models\Legacy;

use App\Models\Company;
use App\Models\Player;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyTransferEvent extends Model
{
    protected $fillable = [
        'company_id',
        'player_id',
        'from_team_id',
        'to_team_id',
        'tournament_id',
        'source_table',
        'legacy_id',
        'event_type',
        'status',
        'payload',
        'event_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'event_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }
}

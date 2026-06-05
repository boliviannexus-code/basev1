<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\TournamentTeamPlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class TournamentTeamPlayer extends Model implements Auditable
{
    /** @use HasFactory<TournamentTeamPlayerFactory> */
    use AuditsCompanyChanges, HasFactory, SoftDeletes;

    public const STATUS_ENABLED = 'enabled';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'company_id',
        'tournament_id',
        'tournament_registration_id',
        'team_id',
        'player_id',
        'team_player_id',
        'status',
        'enabled_at',
        'qr_code_path',
        'qr_code_size',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'enabled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function tournamentRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class);
    }
}

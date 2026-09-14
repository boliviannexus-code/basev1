<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class TournamentTeamSubstitution extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'tournament_id',
        'tournament_registration_id',
        'outgoing_team_id',
        'incoming_team_id',
        'created_by',
        'reason',
        'fixture_matches_updated',
        'standing_adjustments_updated',
        'habilitations_disabled',
        'accreditations_removed',
        'substituted_at',
    ];

    protected function casts(): array
    {
        return [
            'fixture_matches_updated' => 'integer',
            'standing_adjustments_updated' => 'integer',
            'habilitations_disabled' => 'integer',
            'accreditations_removed' => 'integer',
            'substituted_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }

    public function outgoingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'outgoing_team_id');
    }

    public function incomingTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'incoming_team_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

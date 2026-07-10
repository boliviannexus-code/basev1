<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PlayerTransferRequest extends Model implements Auditable
{
    use AuditsCompanyChanges, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'company_id',
        'player_id',
        'division_id',
        'from_team_id',
        'to_team_id',
        'from_team_player_id',
        'to_team_player_id',
        'requested_by',
        'reviewed_by',
        'code',
        'sequence',
        'status',
        'fee_amount',
        'collected_amount',
        'requested_note',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'collected_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
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

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }

    public function fromTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class, 'from_team_player_id');
    }

    public function toTeamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class, 'to_team_player_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}

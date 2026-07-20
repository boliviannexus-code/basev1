<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MeetingAttendance extends Model implements Auditable
{
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'company_id',
        'meeting_id',
        'team_id',
        'tournament_registration_id',
        'present',
        'attended_at',
        'marked_by',
        'permission_requested',
        'permission_requested_at',
        'permission_requested_by',
        'permission_reason',
    ];

    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'attended_at' => 'datetime',
            'permission_requested' => 'boolean',
            'permission_requested_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tournamentRegistration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function permissionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'permission_requested_by');
    }
}

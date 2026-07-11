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
        'present',
        'attended_at',
        'marked_by',
    ];

    protected function casts(): array
    {
        return [
            'present' => 'boolean',
            'attended_at' => 'datetime',
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

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}

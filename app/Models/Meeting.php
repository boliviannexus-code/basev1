<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Meeting extends Model implements Auditable
{
    use AuditsCompanyChanges, HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_FINISHED = 'finished';

    protected $fillable = [
        'company_id',
        'title',
        'meeting_date',
        'status',
        'started_at',
        'finished_at',
        'notes',
        'created_by',
        'finished_by',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }
}

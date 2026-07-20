<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class MatchdayDateFiscal extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = [
        'company_id',
        'matchday_date_id',
        'team_id',
        'start_time',
        'end_time',
        'notes',
    ];

    public function matchdayDate(): BelongsTo
    {
        return $this->belongsTo(MatchdayDate::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}

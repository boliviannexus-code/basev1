<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\TournamentRegistrationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class TournamentRegistration extends Model implements Auditable
{
    /** @use HasFactory<TournamentRegistrationFactory> */
    use AuditsCompanyChanges, HasFactory, SoftDeletes;

    public const SERIES = [
        'unica' => 'Unica',
        'serie_a' => 'Serie A',
        'serie_b' => 'Serie B',
        'serie_c' => 'Serie C',
        'serie_d' => 'Serie D',
    ];

    protected $fillable = [
        'company_id',
        'tournament_id',
        'division_id',
        'category_id',
        'team_id',
        'team_number',
        'series',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'team_number' => 'integer',
        ];
    }

    public function seriesLabel(): string
    {
        return self::SERIES[$this->series] ?? self::SERIES['unica'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DivisionCategory::class, 'category_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tournamentTeamPlayers(): HasMany
    {
        return $this->hasMany(TournamentTeamPlayer::class);
    }

    public function accreditations(): HasMany
    {
        return $this->hasMany(TeamAccreditation::class);
    }
}

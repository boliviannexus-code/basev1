<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Team extends Model implements Auditable
{
    /** @use HasFactory<TeamFactory> */
    use AuditsCompanyChanges, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'name_normalized',
        'name_match_key',
        'founded_at',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'founded_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Team $team): void {
            if ($team->isDirty('name') || blank($team->name_normalized) || blank($team->name_match_key)) {
                $team->name = self::formatName((string) $team->name);
                $team->name_normalized = self::normalizeName($team->name);
                $team->name_match_key = self::matchKey($team->name);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function updateRequests(): HasMany
    {
        return $this->hasMany(TeamUpdateRequest::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function teamPlayers(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    public function tournamentTeamPlayers(): HasMany
    {
        return $this->hasMany(TournamentTeamPlayer::class);
    }

    public function meetingAttendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function tournaments(): BelongsToMany
    {
        return $this->belongsToMany(Tournament::class, 'tournament_registrations')
            ->withPivot(['status', 'notes'])
            ->withTimestamps();
    }

    public function pendingUpdateRequest(): HasOne
    {
        return $this->hasOne(TeamUpdateRequest::class)->where('status', 'pending')->latestOfMany();
    }

    public static function normalizeName(string $name): string
    {
        return str($name)
            ->squish()
            ->lower()
            ->ascii()
            ->toString();
    }

    public static function formatName(string $name): string
    {
        return str($name)
            ->squish()
            ->upper()
            ->toString();
    }

    public static function matchKey(string $name): string
    {
        $normalized = self::normalizeName($name);
        $words = collect(explode(' ', preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?: ''))
            ->filter()
            ->reject(fn (string $word): bool => in_array($word, self::ignoredMatchWords(), true))
            ->values();

        return $words->isNotEmpty() ? $words->implode(' ') : $normalized;
    }

    public static function ignoredMatchWords(): array
    {
        return [
            'a',
            'ac',
            'ad',
            'asociacion',
            'atletico',
            'athletic',
            'c',
            'cd',
            'cf',
            'club',
            'de',
            'del',
            'deportes',
            'deportiva',
            'deportivo',
            'el',
            'equipo',
            'f',
            'fc',
            'futbol',
            'la',
            'las',
            'los',
            'real',
            'sc',
            'sd',
            'sporting',
            'team',
            'union',
        ];
    }
}

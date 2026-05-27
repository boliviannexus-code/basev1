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
            if ($team->isDirty('name') || blank($team->name_normalized)) {
                $team->name = str((string) $team->name)->squish()->toString();
                $team->name_normalized = self::normalizeName($team->name);
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
}

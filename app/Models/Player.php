<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'ci',
        'ci_normalized',
        'first_name',
        'last_name',
        'maternal_name',
        'internal_code',
        'qr_code_path',
        'qr_code_size',
        'photo_path',
        'birth_date',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Player $player): void {
            $player->ci = str((string) $player->ci)->squish()->upper()->toString();
            $player->ci_normalized = self::normalizeCi($player->ci);
            $player->first_name = str((string) $player->first_name)->squish()->title()->toString();
            $player->last_name = str((string) $player->last_name)->squish()->title()->toString();
            $player->maternal_name = filled($player->maternal_name)
                ? str((string) $player->maternal_name)->squish()->title()->toString()
                : null;

            if ($player->exists) {
                $player->internal_code = self::internalCodeFor($player);
            }
        });

        static::created(function (Player $player): void {
            $player->forceFill([
                'internal_code' => self::internalCodeFor($player),
            ])->saveQuietly();
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function teamPlayers(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    public function tournamentTeamPlayers(): HasMany
    {
        return $this->hasMany(TournamentTeamPlayer::class);
    }

    public function biometricFingerprints(): HasMany
    {
        return $this->hasMany(BiometricFingerprint::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name.' '.$this->maternal_name);
    }

    public function age(?CarbonInterface $at = null): ?int
    {
        return $this->birth_date ? (int) $this->birth_date->diffInYears($at ?? now()) : null;
    }

    public function scopeForCompany(Builder $query, ?int $companyId): Builder
    {
        return $query->when($companyId !== null, fn (Builder $query): Builder => $query
            ->whereHas('teamPlayers', fn (Builder $teamPlayers): Builder => $teamPlayers
                ->where('team_players.company_id', $companyId)
                ->where('team_players.status', TeamPlayer::STATUS_ACTIVE)
                ->whereNull('team_players.deleted_at')
            ));
    }

    public static function normalizeCi(string $ci): string
    {
        return str($ci)
            ->squish()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->toString();
    }

    public static function internalCodeForId(int $id): string
    {
        return 'N'.str_pad((string) ($id + 99), 5, '0', STR_PAD_LEFT);
    }

    public static function internalCodeFor(self $player): string
    {
        return self::internalCodeForId((int) $player->id);
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Player extends Model
{
    /** @use HasFactory<PlayerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ci',
        'ci_normalized',
        'first_name',
        'last_name',
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

            if ($player->exists) {
                $player->internal_code = self::internalCodeForId((int) $player->id);
            }
        });

        static::created(function (Player $player): void {
            $player->forceFill([
                'internal_code' => self::internalCodeForId((int) $player->id),
            ])->saveQuietly();
        });
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
        return trim($this->first_name.' '.$this->last_name);
    }

    public function age(?CarbonInterface $at = null): ?int
    {
        return $this->birth_date ? (int) $this->birth_date->diffInYears($at ?? now()) : null;
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
        return 'Nex'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }
}

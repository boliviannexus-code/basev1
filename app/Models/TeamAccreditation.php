<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAccreditation extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'tournament_id',
        'tournament_registration_id',
        'team_id',
        'player_id',
        'slot',
        'ci',
        'ci_normalized',
        'first_name',
        'last_name',
        'maternal_name',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (TeamAccreditation $accreditation): void {
            $accreditation->ci = str((string) $accreditation->ci)->squish()->upper()->toString();
            $accreditation->ci_normalized = Player::normalizeCi($accreditation->ci);
            $accreditation->first_name = str((string) $accreditation->first_name)->squish()->title()->toString();
            $accreditation->last_name = str((string) $accreditation->last_name)->squish()->title()->toString();
            $accreditation->maternal_name = filled($accreditation->maternal_name)
                ? str((string) $accreditation->maternal_name)->squish()->title()->toString()
                : null;
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(TournamentRegistration::class, 'tournament_registration_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name.' '.$this->maternal_name);
    }
}

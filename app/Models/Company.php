<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    /** @use HasFactory<CompanyFactory> */
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'name',
        'code',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'foundation_date',
        'legal_personality',
        'interest_data',
        'logo_path',
        'report_footer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'foundation_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            $company->code = self::normalizeCode((string) $company->code);
        });
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class);
    }

    public function divisionCategories(): HasMany
    {
        return $this->hasMany(DivisionCategory::class);
    }

    public function teamPlayers(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function tournamentTeamPlayers(): HasMany
    {
        return $this->hasMany(TournamentTeamPlayer::class);
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function playerTransferSetting(): HasOne
    {
        return $this->hasOne(PlayerTransferSetting::class);
    }

    public function leagueSetting(): HasOne
    {
        return $this->hasOne(LeagueSetting::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getLogoDisplayUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($this->logo_path)) {
            return $this->logo_url;
        }

        $mimeType = $disk->mimeType($this->logo_path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($disk->get($this->logo_path));
    }

    public static function normalizeCode(string $code): string
    {
        return str($code)
            ->squish()
            ->upper()
            ->replaceMatches('/[^A-Z]/', '')
            ->limit(3, '')
            ->toString();
    }
}

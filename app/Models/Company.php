<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    /** @use HasFactory<CompanyFactory> */
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'name',
        'code',
        'subdomain',
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
        'public_page_title',
        'public_page_summary',
        'public_page_body',
        'public_contact_text',
        'public_whatsapp',
        'public_facebook_url',
        'public_instagram_url',
        'public_tiktok_url',
        'public_youtube_url',
        'public_banner_path',
        'public_image_one_path',
        'public_image_two_path',
        'public_page_is_enabled',
        'interface_primary_color',
        'interface_secondary_color',
        'interface_accent_color',
        'interface_sidebar_color',
        'interface_login_background_color',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'foundation_date' => 'date',
            'public_page_is_enabled' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Company $company): void {
            $company->code = self::normalizeCode((string) $company->code);
            $company->subdomain = self::normalizeSubdomain((string) $company->subdomain);
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

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
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

    public function matchControlItems(): HasMany
    {
        return $this->hasMany(MatchControlItem::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getLogoDisplayUrlAttribute(): ?string
    {
        $logoPath = $this->logo_local_path;

        if ($logoPath) {
            $mimeType = File::mimeType($logoPath) ?: 'image/png';

            return 'data:'.$mimeType.';base64,'.base64_encode(File::get($logoPath));
        }

        return null;
    }

    public function getLogoLocalPathAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $storagePath = storage_path('app/public/'.ltrim($this->logo_path, '/'));

        if (File::exists($storagePath)) {
            return $storagePath;
        }

        $publicPath = public_path('storage/'.ltrim($this->logo_path, '/'));

        if (File::exists($publicPath)) {
            return $publicPath;
        }

        return null;
    }

    public function publicImageUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
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

    public static function normalizeSubdomain(string $subdomain): ?string
    {
        $normalized = str($subdomain)
            ->squish()
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9-]/', '')
            ->replaceMatches('/-+/', '-')
            ->trim('-')
            ->toString();

        return $normalized !== '' ? $normalized : null;
    }

    public function interfaceThemeVariables(): array
    {
        return [
            'primary' => $this->interface_primary_color,
            'secondary' => $this->interface_secondary_color,
            'accent' => $this->interface_accent_color,
            'sidebar' => $this->interface_sidebar_color,
            'login_background' => $this->interface_login_background_color,
        ];
    }
}

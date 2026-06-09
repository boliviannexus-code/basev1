<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    /** @use HasFactory<CompanyFactory> */
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'phone',
        'whatsapp',
        'email',
        'website',
        'address',
        'location_reference',
        'city',
        'country',
        'latitude',
        'longitude',
        'logo_path',
        'logo',
        'cover_image',
        'public_slug',
        'public_name',
        'public_description',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'report_footer',
        'is_active',
        'is_public_enabled',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
            'is_public_enabled' => 'boolean',
            'is_online_enabled_by_admin' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function reservationChannels(): HasMany
    {
        return $this->hasMany(ReservationChannel::class);
    }

    public function countries(): HasMany
    {
        return $this->hasMany(Country::class);
    }

    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function checkInGroups(): HasMany
    {
        return $this->hasMany(CheckInGroup::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(Stay::class);
    }

    public function accountStatements(): HasMany
    {
        return $this->hasMany(AccountStatement::class);
    }

    public function extraChargeCategories(): HasMany
    {
        return $this->hasMany(ExtraChargeCategory::class);
    }

    public function packageServices(): HasMany
    {
        return $this->hasMany(PackageService::class);
    }

    public function accommodationPackages(): HasMany
    {
        return $this->hasMany(AccommodationPackage::class);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->whereNotNull('public_slug')
            ->where('public_slug', '!=', '')
            ->where('is_public_enabled', true)
            ->where('is_online_enabled_by_admin', true);
    }

    public function isPublicPageVisible(): bool
    {
        return $this->public_slug !== null
            && $this->public_slug !== ''
            && $this->is_public_enabled
            && $this->is_online_enabled_by_admin;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}

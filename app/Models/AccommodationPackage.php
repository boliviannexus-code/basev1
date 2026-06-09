<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccommodationPackage extends Model
{
    use BelongsToCompany;

    public const INCLUSION_TYPES = [
        'included',
        'optional_paid',
        'not_included',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'short_description',
        'badges',
        'commercial_description',
        'conditions',
        'price',
        'currency',
        'price_display_text',
        'included_people',
        'max_people',
        'extra_person_price',
        'requires_full_private_space',
        'nights_included',
        'is_active',
        'is_featured',
        'sort_order',
        'main_image',
        'video_url',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'badges' => 'array',
            'included_people' => 'integer',
            'max_people' => 'integer',
            'extra_person_price' => 'decimal:2',
            'requires_full_private_space' => 'boolean',
            'nights_included' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'accommodation_package_space', 'package_id', 'space_id')
            ->withTimestamps();
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(PackageService::class, 'accommodation_package_service', 'package_id', 'service_id')
            ->withPivot(['id', 'inclusion_type', 'custom_name', 'custom_description', 'additional_price', 'sort_order'])
            ->withTimestamps();
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'package_id');
    }
}

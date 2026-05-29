<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use OwenIt\Auditing\Contracts\Auditable;

class Tour extends Model implements Auditable
{
    use AuditsCompanyChanges, HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_ARCHIVED = 'archived';

    public const REVIEW_DRAFT = 'draft';

    public const REVIEW_PENDING = 'pending';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Borrador',
        self::STATUS_ACTIVE => 'Activo',
        self::STATUS_INACTIVE => 'Inactivo',
        self::STATUS_ARCHIVED => 'Archivado',
    ];

    public const REVIEW_STATUSES = [
        self::REVIEW_DRAFT => 'Borrador',
        self::REVIEW_PENDING => 'En revision',
        self::REVIEW_APPROVED => 'Aprobado',
        self::REVIEW_REJECTED => 'Pendiente de correccion',
    ];

    public const STEPS = [
        1 => 'Identificacion',
        2 => 'Descripciones',
        3 => 'Ubicacion',
        4 => 'Palabras clave',
        5 => 'Incluye / No incluye',
        6 => 'Servicios',
        7 => 'Informacion adicional',
        8 => 'Imagenes',
        9 => 'Operacion',
        10 => 'Itinerario',
    ];

    public const TOTAL_STEPS = 10;

    protected $fillable = [
        'company_id',
        'category_id',
        'guide_type_id',
        'transport_type_id',
        'title',
        'reference_code',
        'name',
        'short_description',
        'full_description',
        'description',
        'country',
        'city',
        'location_text',
        'keywords',
        'includes',
        'excludes',
        'duration',
        'start_time',
        'end_time',
        'meeting_point',
        'booking_deadline_value',
        'booking_deadline_unit',
        'capacity',
        'included',
        'not_included',
        'requirements',
        'includes_food',
        'food_details',
        'includes_transport',
        'pets_allowed',
        'pets_policy',
        'prohibitions',
        'recommendations',
        'emergency_phone',
        'activity_type',
        'bookings_enabled',
        'status',
        'review_status',
        'rejection_points',
        'correction_history',
        'reviewed_by',
        'reviewed_at',
        'current_step',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'includes_food' => 'boolean',
            'includes_transport' => 'boolean',
            'pets_allowed' => 'boolean',
            'bookings_enabled' => 'boolean',
            'rejection_points' => 'array',
            'correction_history' => 'array',
            'reviewed_at' => 'datetime',
            'current_step' => 'integer',
            'capacity' => 'integer',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function guideType(): BelongsTo
    {
        return $this->belongsTo(GuideType::class);
    }

    public function transportType(): BelongsTo
    {
        return $this->belongsTo(TransportType::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(TourImage::class)->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id');
    }

    public function mainImage(): HasMany
    {
        return $this->hasMany(TourImage::class)->where('is_main', true);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TourPrice::class)->orderBy('min_people');
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TourAvailability::class);
    }

    public function itineraryDays(): HasMany
    {
        return $this->hasMany(TourItineraryDay::class)->orderBy('day_number');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(TourBooking::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(TourReview::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getReviewStatusLabelAttribute(): string
    {
        return self::REVIEW_STATUSES[$this->review_status] ?? ucfirst((string) $this->review_status);
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: $this->name;
    }

    public function scopePubliclyBookable(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('review_status', self::REVIEW_APPROVED)
            ->where('bookings_enabled', true);
    }
}

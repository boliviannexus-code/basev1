<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TourBooking extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendiente',
        self::STATUS_CONFIRMED => 'Confirmada',
        self::STATUS_CANCELLED => 'Cancelada',
        self::STATUS_COMPLETED => 'Completada',
    ];

    protected $fillable = [
        'user_id',
        'tour_id',
        'tour_availability_id',
        'booking_code',
        'travel_date',
        'people',
        'first_name',
        'last_name',
        'email',
        'phone',
        'country',
        'special_requirements',
        'unit_price_usd',
        'total_usd',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'travel_date' => 'date',
            'people' => 'integer',
            'unit_price_usd' => 'decimal:2',
            'total_usd' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(TourAvailability::class, 'tour_availability_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(TourReview::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}

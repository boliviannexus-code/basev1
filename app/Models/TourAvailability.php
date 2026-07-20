<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourAvailability extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SOLD_OUT = 'sold_out';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_AVAILABLE => 'Disponible',
        self::STATUS_CLOSED => 'Cerrado',
        self::STATUS_SOLD_OUT => 'Agotado',
        self::STATUS_CANCELLED => 'Cancelado',
    ];

    protected $fillable = [
        'tour_id',
        'date',
        'status',
        'capacity',
        'booked_count',
        'restrictions',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'capacity' => 'integer',
            'booked_count' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(TourAvailabilityPrice::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}

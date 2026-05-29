<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourAvailabilityPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_availability_id',
        'tour_price_id',
        'title',
        'min_people',
        'max_people',
        'price_usd',
    ];

    protected function casts(): array
    {
        return [
            'tour_price_id' => 'integer',
            'min_people' => 'integer',
            'max_people' => 'integer',
            'price_usd' => 'decimal:2',
        ];
    }

    public function availability(): BelongsTo
    {
        return $this->belongsTo(TourAvailability::class, 'tour_availability_id');
    }

    public function tourPrice(): BelongsTo
    {
        return $this->belongsTo(TourPrice::class);
    }
}

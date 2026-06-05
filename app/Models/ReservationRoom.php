<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationRoom extends Model
{
    protected $fillable = [
        'reservation_id',
        'space_room_id',
        'occupancy_block_id',
        'capacity',
        'price_per_night',
        'subtotal_amount',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'price_per_night' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(SpaceRoom::class, 'space_room_id');
    }

    public function occupancyBlock(): BelongsTo
    {
        return $this->belongsTo(OccupancyBlock::class);
    }
}

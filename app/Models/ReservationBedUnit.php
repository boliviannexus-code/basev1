<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationBedUnit extends Model
{
    protected $fillable = [
        'reservation_id',
        'room_bed_unit_id',
        'occupancy_block_id',
        'guest_name',
        'price_per_night',
        'subtotal_amount',
    ];

    protected function casts(): array
    {
        return [
            'price_per_night' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function bedUnit(): BelongsTo
    {
        return $this->belongsTo(RoomBedUnit::class, 'room_bed_unit_id');
    }

    public function occupancyBlock(): BelongsTo
    {
        return $this->belongsTo(OccupancyBlock::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OccupancyBlock extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const TYPES = [
        'manual_block',
        'maintenance',
        'owner_use',
        'unavailable',
    ];

    public const STATUSES = [
        'active',
        'cancelled',
    ];

    protected $fillable = [
        'company_id',
        'space_id',
        'space_room_id',
        'room_bed_unit_id',
        'type',
        'status',
        'title',
        'description',
        'start_date',
        'end_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(SpaceRoom::class, 'space_room_id');
    }

    public function bedUnit(): BelongsTo
    {
        return $this->belongsTo(RoomBedUnit::class, 'room_bed_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(Reservation::class, 'occupancy_block_id');
    }

    public function reservationRoom(): HasOne
    {
        return $this->hasOne(ReservationRoom::class, 'occupancy_block_id');
    }

    public function reservationBedUnit(): HasOne
    {
        return $this->hasOne(ReservationBedUnit::class, 'occupancy_block_id');
    }
}

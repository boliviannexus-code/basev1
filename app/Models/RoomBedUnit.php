<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomBedUnit extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = [
        'active',
        'inactive',
    ];

    protected $fillable = [
        'company_id',
        'space_room_id',
        'room_bed_id',
        'bed_type_id',
        'label',
        'code',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(SpaceRoom::class, 'space_room_id');
    }

    public function roomBed(): BelongsTo
    {
        return $this->belongsTo(RoomBed::class);
    }

    public function bedType(): BelongsTo
    {
        return $this->belongsTo(BedType::class);
    }

    public function occupancyBlocks(): HasMany
    {
        return $this->hasMany(OccupancyBlock::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationBedUnit::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(Stay::class);
    }
}

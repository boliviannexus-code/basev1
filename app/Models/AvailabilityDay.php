<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvailabilityDay extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUSES = [
        'available',
        'closed',
    ];

    protected $fillable = [
        'company_id',
        'space_id',
        'space_room_id',
        'room_bed_unit_id',
        'date',
        'price',
        'status',
        'is_public_online',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
            'is_public_online' => 'boolean',
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
}

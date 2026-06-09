<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AvailabilityStatus extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = [
        'available',
        'closed',
        'occupied',
        'reserved',
    ];

    public const SOURCES = [
        'manual',
        'reservation',
        'system',
        'check_in',
    ];

    protected $fillable = [
        'company_id',
        'space_id',
        'space_room_id',
        'room_bed_unit_id',
        'date',
        'status',
        'source',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
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

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

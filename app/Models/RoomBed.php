<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomBed extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'space_room_id',
        'bed_type_id',
        'quantity',
        'capacity_per_bed',
        'total_capacity',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'capacity_per_bed' => 'integer',
            'total_capacity' => 'integer',
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

    public function bedType(): BelongsTo
    {
        return $this->belongsTo(BedType::class);
    }

    public function bedUnits(): HasMany
    {
        return $this->hasMany(RoomBedUnit::class)->orderBy('sort_order')->orderBy('id');
    }
}

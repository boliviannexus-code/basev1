<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpaceRoom extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'space_id',
        'name',
        'room_number',
        'title',
        'description',
        'bathroom_type_id',
        'max_capacity',
        'photos_skipped',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'max_capacity' => 'integer',
            'photos_skipped' => 'boolean',
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

    public function bathroomType(): BelongsTo
    {
        return $this->belongsTo(BathroomType::class);
    }

    public function beds(): HasMany
    {
        return $this->hasMany(RoomBed::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomPhoto::class);
    }

    public function roomServices(): BelongsToMany
    {
        return $this->belongsToMany(RoomService::class, 'room_room_service')
            ->withPivot(['id', 'company_id'])
            ->withTimestamps();
    }

    public function occupancyBlocks(): HasMany
    {
        return $this->hasMany(OccupancyBlock::class);
    }

    public function availabilityDays(): HasMany
    {
        return $this->hasMany(AvailabilityDay::class);
    }
}

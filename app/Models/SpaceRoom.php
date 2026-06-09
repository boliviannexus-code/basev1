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
        'sale_mode',
        'photos_skipped',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_capacity' => 'integer',
            'photos_skipped' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public const SALE_MODES = [
        'full_room',
        'bed_unit',
        'flexible',
    ];

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

    public function bedUnits(): HasMany
    {
        return $this->hasMany(RoomBedUnit::class)->orderBy('sort_order')->orderBy('id');
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

    public function availabilityStatuses(): HasMany
    {
        return $this->hasMany(AvailabilityStatus::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function stays(): HasMany
    {
        return $this->hasMany(Stay::class);
    }

    public function reservationItems(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function hasFutureBlockingReservation(): bool
    {
        $today = now()->toDateString();
        $statuses = Reservation::BLOCKING_STATUSES;

        return $this->reservations()
            ->whereIn('status', $statuses)
            ->whereDate('check_out', '>', $today)
            ->exists()
            || $this->reservationItems()
                ->whereHas('reservation', fn ($query) => $query
                    ->whereIn('status', $statuses)
                    ->whereDate('check_out', '>', $today))
                ->exists()
            || $this->bedUnits()
                ->whereHas('reservationItems.reservation', fn ($query) => $query
                    ->whereIn('status', $statuses)
                    ->whereDate('check_out', '>', $today))
                ->exists();
    }
}

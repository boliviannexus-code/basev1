<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = [
        'pending_data',
        'pending_payment',
        'payment_under_review',
        'confirmed',
        'checked_in',
        'rejected',
        'cancelled',
        'expired',
        'no_show',
    ];

    public const BLOCKING_STATUSES = [
        'pending_payment',
        'payment_under_review',
        'confirmed',
    ];

    public const PAYMENT_STATUSES = [
        'pending',
        'submitted',
        'validated',
        'rejected',
    ];

    public const BOOKING_TYPES = [
        'normal',
        'package',
    ];

    protected $fillable = [
        'company_id',
        'reservation_group_id',
        'user_id',
        'space_id',
        'space_room_id',
        'occupancy_block_id',
        'package_id',
        'reservation_channel_id',
        'booking_type',
        'package_snapshot',
        'package_price',
        'included_people',
        'extra_people',
        'extra_people_total',
        'package_extra_nights',
        'package_extra_nights_total',
        'code',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_country',
        'guest_document',
        'check_in',
        'check_out',
        'nights',
        'guests',
        'price_per_person',
        'subtotal_amount',
        'total_amount',
        'advance_amount',
        'deposit_amount',
        'balance_amount',
        'currency',
        'breakfast_included',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_proof_path',
        'hold_expires_at',
        'guest_notes',
        'payment_validated_at',
        'payment_validated_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'nights' => 'integer',
            'guests' => 'integer',
            'price_per_person' => 'decimal:2',
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'breakfast_included' => 'boolean',
            'package_snapshot' => 'array',
            'package_price' => 'decimal:2',
            'included_people' => 'integer',
            'extra_people' => 'integer',
            'extra_people_total' => 'decimal:2',
            'package_extra_nights' => 'integer',
            'package_extra_nights_total' => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'payment_validated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(SpaceRoom::class, 'space_room_id');
    }

    public function roomItems(): HasMany
    {
        return $this->hasMany(ReservationRoom::class);
    }

    public function bedUnitItems(): HasMany
    {
        return $this->hasMany(ReservationBedUnit::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(SpaceRoom::class, 'reservation_rooms')
            ->withPivot(['occupancy_block_id', 'capacity', 'price_per_night', 'subtotal_amount'])
            ->withTimestamps();
    }

    public function bedUnits(): BelongsToMany
    {
        return $this->belongsToMany(RoomBedUnit::class, 'reservation_bed_units')
            ->withPivot(['occupancy_block_id', 'guest_name', 'price_per_night', 'subtotal_amount'])
            ->withTimestamps();
    }

    public function occupancyBlock(): BelongsTo
    {
        return $this->belongsTo(OccupancyBlock::class);
    }

    public function accommodationPackage(): BelongsTo
    {
        return $this->belongsTo(AccommodationPackage::class, 'package_id');
    }

    public function reservationChannel(): BelongsTo
    {
        return $this->belongsTo(ReservationChannel::class);
    }

    public function extraCharges(): HasMany
    {
        return $this->hasMany(ReservationExtraCharge::class);
    }

    public function accountStatement(): HasOne
    {
        return $this->hasOne(AccountStatement::class);
    }

    public function paymentValidator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_validated_by');
    }

    public function canBeEditedByGuest(): bool
    {
        return $this->status === 'pending_payment' && $this->payment_status === 'pending';
    }

    public function canSubmitPaymentProof(): bool
    {
        return in_array($this->status, ['pending_payment', 'payment_under_review'], true)
            && in_array($this->payment_status, ['pending', 'submitted', 'rejected'], true);
    }

    public function isClosedWithoutStay(): bool
    {
        return in_array($this->status, ['cancelled', 'rejected', 'expired', 'no_show'], true);
    }

    public function shouldBlockAvailability(): bool
    {
        if (! in_array($this->status, self::BLOCKING_STATUSES, true)) {
            return false;
        }

        return $this->status !== 'pending_payment'
            || $this->hold_expires_at === null
            || $this->hold_expires_at->isFuture();
    }

    public function hasExpiredTemporaryHold(): bool
    {
        return $this->status === 'pending_payment'
            && $this->payment_status === 'pending'
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isPast();
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = [
        'pending_data',
        'pending_payment',
        'payment_under_review',
        'confirmed',
        'rejected',
        'cancelled',
        'expired',
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

    protected $fillable = [
        'company_id',
        'user_id',
        'space_id',
        'space_room_id',
        'occupancy_block_id',
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
        'balance_amount',
        'currency',
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
            'balance_amount' => 'decimal:2',
            'hold_expires_at' => 'datetime',
            'payment_validated_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(SpaceRoom::class, 'reservation_rooms')
            ->withPivot(['occupancy_block_id', 'capacity', 'price_per_night', 'subtotal_amount'])
            ->withTimestamps();
    }

    public function occupancyBlock(): BelongsTo
    {
        return $this->belongsTo(OccupancyBlock::class);
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

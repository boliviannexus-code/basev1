<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountStatement extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUSES = [
        'pending',
        'partial',
        'paid',
    ];

    protected $fillable = [
        'company_id',
        'stay_id',
        'reservation_id',
        'reservation_group_id',
        'currency',
        'subtotal',
        'discount_total',
        'extra_charges_total',
        'payments_total',
        'balance',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'extra_charges_total' => 'decimal:2',
            'payments_total' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function stay(): BelongsTo
    {
        return $this->belongsTo(Stay::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountStatementItem::class);
    }
}

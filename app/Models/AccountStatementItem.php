<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountStatementItem extends Model
{
    use BelongsToCompany, HasFactory;

    public const TYPES = [
        'lodging_night',
        'breakfast',
        'extra',
        'discount',
        'payment',
        'adjustment',
    ];

    public const SOURCES = [
        'check_in',
        'manual',
        'system',
    ];

    public const STATUSES = [
        'active',
        'cancelled',
    ];

    protected $fillable = [
        'company_id',
        'account_statement_id',
        'stay_id',
        'reservation_id',
        'reservation_group_id',
        'extra_charge_category_id',
        'date',
        'type',
        'description',
        'quantity',
        'unit_price',
        'total',
        'currency',
        'source',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountStatement(): BelongsTo
    {
        return $this->belongsTo(AccountStatement::class);
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

    public function extraChargeCategory(): BelongsTo
    {
        return $this->belongsTo(ExtraChargeCategory::class, 'extra_charge_category_id');
    }
}

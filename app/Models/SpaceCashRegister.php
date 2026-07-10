<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpaceCashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'opening_amount',
        'closing_amount',
        'opened_at',
        'closed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function expenses(): HasMany
    {
        return $this->hasMany(SpaceCashExpense::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(SpaceCashIncome::class);
    }

    public function lodgingPayments(): HasMany
    {
        return $this->hasMany(SpaceCashLodgingPayment::class);
    }

    public function reservationPayments(): HasMany
    {
        return $this->hasMany(SpaceCashReservationPayment::class);
    }
}

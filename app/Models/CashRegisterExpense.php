<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashRegisterExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'cash_register_id',
        'point_of_sale_id',
        'user_id',
        'extra_charge_category_id',
        'payment_method_id',
        'responsible_name',
        'detail',
        'quantity',
        'amount',
        'spent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'quantity' => 'decimal:2',
            'spent_at' => 'datetime',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExtraChargeCategory::class, 'extra_charge_category_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}

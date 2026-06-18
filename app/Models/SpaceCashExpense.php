<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceCashExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'space_cash_register_id',
        'user_id',
        'extra_charge_category_id',
        'responsible_name',
        'detail',
        'amount',
        'spent_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'datetime',
        ];
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(SpaceCashRegister::class, 'space_cash_register_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExtraChargeCategory::class, 'extra_charge_category_id');
    }
}

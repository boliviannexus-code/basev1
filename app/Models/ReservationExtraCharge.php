<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationExtraCharge extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'reservation_id',
        'extra_charge_category_id',
        'date',
        'detail',
        'quantity',
        'unit_price',
        'total',
        'currency',
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

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExtraChargeCategory::class, 'extra_charge_category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleDetail extends Model
{
    protected $fillable = [
        'sale_id',
        'product_id',
        'presentation_id',
        'product_name',
        'presentation_name',
        'package_quantity',
        'units_per_package',
        'quantity',
        'unit_price',
        'discount',
        'subtotal',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}

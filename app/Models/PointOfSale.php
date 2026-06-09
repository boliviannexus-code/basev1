<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointOfSale extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'warehouse_id',
        'name',
        'code',
        'receipt_prefix',
        'sequence_number',
        'receipt_next_number',
        'receipt_digits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'receipt_next_number' => 'integer',
            'receipt_digits' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }
}

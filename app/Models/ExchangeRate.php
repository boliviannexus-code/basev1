<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'from_currency',
        'to_currency',
        'rate',
        'effective_date',
        'notes',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('from_currency', 'USD')
            ->where('to_currency', 'BOB')
            ->latest('effective_date')
            ->latest('id');
    }

    public static function currentForCompany(int $companyId): ?self
    {
        return self::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->current()
            ->first();
    }

    public static function currentRateForCompany(int $companyId): ?float
    {
        $rate = self::currentForCompany($companyId);

        return $rate ? (float) $rate->rate : null;
    }
}

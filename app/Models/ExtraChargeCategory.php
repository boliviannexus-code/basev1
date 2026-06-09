<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExtraChargeCategory extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const DEFAULTS = [
        ['name' => 'Lavandería', 'sort_order' => 10],
        ['name' => 'Agua', 'sort_order' => 20],
        ['name' => 'Tour', 'sort_order' => 30],
        ['name' => 'Otros', 'sort_order' => 40],
    ];

    protected $fillable = [
        'company_id',
        'name',
        'default_unit_price',
        'currency',
        'is_active',
        'is_protected',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_protected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountStatementItems(): HasMany
    {
        return $this->hasMany(AccountStatementItem::class);
    }

    public function reservationExtraCharges(): HasMany
    {
        return $this->hasMany(ReservationExtraCharge::class);
    }

    public static function ensureDefaultsForCompany(int $companyId): void
    {
        if (self::query()->where('company_id', $companyId)->withTrashed()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $default) {
            self::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'name' => $default['name'],
                ],
                [
                    'default_unit_price' => 0,
                    'currency' => 'BOB',
                    'is_active' => true,
                    'is_protected' => true,
                    'sort_order' => $default['sort_order'],
                ],
            );
        }
    }
}

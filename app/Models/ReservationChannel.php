<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ReservationChannel extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const TYPES = [
        'direct',
        'ota',
        'agency',
        'corporate',
        'other',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'commission_percent',
        'notes',
        'is_active',
        'is_protected',
        'sort_order',
    ];

    public const DEFAULTS = [
        ['name' => 'Walk-in', 'type' => 'direct', 'sort_order' => 1, 'is_protected' => true],
        ['name' => 'Booking', 'type' => 'ota', 'sort_order' => 2, 'is_protected' => false],
        ['name' => 'Hostelworld', 'type' => 'ota', 'sort_order' => 3, 'is_protected' => false],
        ['name' => 'Agencia', 'type' => 'agency', 'sort_order' => 4, 'is_protected' => false],
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'is_active' => 'boolean',
            'is_protected' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function checkInGroups(): HasMany
    {
        return $this->hasMany(CheckInGroup::class);
    }

    public static function ensureDefaultsForCompany(int $companyId): void
    {
        foreach (self::DEFAULTS as $channel) {
            self::query()->firstOrCreate(
                [
                    'company_id' => $companyId,
                    'slug' => Str::slug($channel['name']),
                ],
                [
                    'name' => $channel['name'],
                    'type' => $channel['type'],
                    'is_active' => true,
                    'is_protected' => $channel['is_protected'],
                    'sort_order' => $channel['sort_order'],
                ],
            );
        }
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stay extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const CURRENCIES = [
        'BOB',
        'USD',
    ];

    public const STATUSES = [
        'occupied',
        'checked_out',
        'cancelled',
    ];

    protected $fillable = [
        'company_id',
        'check_in_group_id',
        'holder_guest_id',
        'space_id',
        'space_room_id',
        'room_bed_unit_id',
        'people_count',
        'check_in_date',
        'check_out_date',
        'nights',
        'price_per_night_bob',
        'price_per_night_usd',
        'exchange_rate',
        'currency',
        'breakfast_included',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'people_count' => 'integer',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'nights' => 'integer',
            'price_per_night_bob' => 'decimal:2',
            'price_per_night_usd' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'breakfast_included' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Stay $stay): void {
            if ((! $stay->nights || $stay->nights < 1) && $stay->check_in_date && $stay->check_out_date) {
                $stay->nights = (int) CarbonImmutable::parse($stay->check_in_date)
                    ->diffInDays(CarbonImmutable::parse($stay->check_out_date));
            }
        });

    }

    public function lodgingUnitPrice(): string
    {
        return $this->currency === 'USD'
            ? (string) ($this->price_per_night_usd ?? 0)
            : (string) ($this->price_per_night_bob ?? 0);
    }

    public function lodgingNightItems(): array
    {
        $start = CarbonImmutable::parse($this->check_in_date);

        return collect(range(0, max((int) $this->nights - 1, 0)))
            ->map(fn (int $offset): array => [
                'company_id' => $this->company_id,
                'stay_id' => $this->id,
                'date' => $start->addDays($offset)->toDateString(),
                'type' => 'lodging_night',
                'description' => 'Hospedaje noche '.($offset + 1),
                'quantity' => 1,
                'unit_price' => $this->lodgingUnitPrice(),
                'total' => $this->lodgingUnitPrice(),
                'currency' => $this->currency,
                'source' => 'check_in',
            ])
            ->all();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function checkInGroup(): BelongsTo
    {
        return $this->belongsTo(CheckInGroup::class);
    }

    public function holderGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'holder_guest_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(SpaceRoom::class, 'space_room_id');
    }

    public function bedUnit(): BelongsTo
    {
        return $this->belongsTo(RoomBedUnit::class, 'room_bed_unit_id');
    }

    public function guests(): BelongsToMany
    {
        return $this->belongsToMany(Guest::class, 'stay_guests')
            ->withPivot(['id', 'company_id', 'is_holder'])
            ->withTimestamps();
    }

    public function accountStatement(): HasOne
    {
        return $this->hasOne(AccountStatement::class);
    }

    public function checkOutAlertSnoozes(): HasMany
    {
        return $this->hasMany(CheckOutAlertSnooze::class);
    }

    public function checkOutAlertEscalation(): HasOne
    {
        return $this->hasOne(CheckOutAlertEscalation::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guest extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const DOCUMENT_TYPES = [
        'passport',
        'dni',
        'ci',
        'other',
    ];

    protected $fillable = [
        'company_id',
        'document_type',
        'document_number',
        'first_name',
        'last_name',
        'birth_country_id',
        'birth_date',
        'phone',
        'email',
        'created_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function birthCountry(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'birth_country_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checkInGroups(): HasMany
    {
        return $this->hasMany(CheckInGroup::class, 'main_guest_id');
    }

    public function holderStays(): HasMany
    {
        return $this->hasMany(Stay::class, 'holder_guest_id');
    }

    public function stays(): BelongsToMany
    {
        return $this->belongsToMany(Stay::class, 'stay_guests')
            ->withPivot(['id', 'company_id', 'is_holder'])
            ->withTimestamps();
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }
}

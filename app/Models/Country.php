<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'iso_code',
        'name',
        'is_active',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function birthGuests(): HasMany
    {
        return $this->hasMany(Guest::class, 'birth_country_id');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when(filled($search), function (Builder $query) use ($search): Builder {
            $term = mb_strtolower(trim((string) $search));

            return $query->where(function (Builder $query) use ($term): void {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(iso_code) LIKE ?', ["%{$term}%"]);
            });
        });
    }

    public function scopeForCheckInSearch(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}

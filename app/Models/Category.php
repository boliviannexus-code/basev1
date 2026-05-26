<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class Category extends Model implements Auditable
{
    /** @use HasFactory<CategoryFactory> */
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }
}

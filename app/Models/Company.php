<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Company extends Model implements Auditable
{
    /** @use HasFactory<CompanyFactory> */
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'tax_id',
        'phone',
        'email',
        'address',
        'city',
        'country',
        'logo_path',
        'report_footer',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}

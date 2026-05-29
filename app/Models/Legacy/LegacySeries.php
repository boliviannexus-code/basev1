<?php

namespace App\Models\Legacy;

use App\Models\Company;
use App\Models\DivisionCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacySeries extends Model
{
    protected $table = 'legacy_series';

    protected $fillable = [
        'company_id',
        'division_category_id',
        'legacy_id',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function divisionCategory(): BelongsTo
    {
        return $this->belongsTo(DivisionCategory::class);
    }
}

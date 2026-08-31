<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ExtraCharge extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = ['company_id', 'name', 'description', 'amount_per_team', 'installments', 'is_active'];

    protected function casts(): array
    {
        return ['amount_per_team' => 'decimal:2', 'installments' => 'integer', 'is_active' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function assignments(): HasMany { return $this->hasMany(ExtraChargeAssignment::class); }
    public function appliedInstallments(): HasMany { return $this->hasMany(ExtraChargeInstallment::class); }
}

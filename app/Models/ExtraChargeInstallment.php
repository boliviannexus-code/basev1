<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class ExtraChargeInstallment extends Model implements Auditable
{
    use AuditsCompanyChanges;

    protected $fillable = ['company_id', 'extra_charge_id', 'matchday_id', 'tournament_id', 'team_id', 'installment_number', 'amount', 'status', 'voided_at', 'voided_by'];
    protected function casts(): array { return ['installment_number' => 'integer', 'amount' => 'decimal:2', 'voided_at' => 'datetime']; }
    public function extraCharge(): BelongsTo { return $this->belongsTo(ExtraCharge::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
}

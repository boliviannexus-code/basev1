<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class CourtFeeItem extends Model implements Auditable
{
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = ['court_fee_id', 'name', 'cost'];

    protected function casts(): array
    {
        return ['cost' => 'decimal:2'];
    }

    public function courtFee(): BelongsTo
    {
        return $this->belongsTo(CourtFee::class);
    }
}

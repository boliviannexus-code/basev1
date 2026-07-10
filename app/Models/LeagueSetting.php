<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class LeagueSetting extends Model implements Auditable
{
    use AuditsCompanyChanges;

    public const COST_FIELDS = [
        'transfer_fee',
        'yellow_card_fee',
        'red_card_fee',
        'court_fee',
        'medical_fee',
        'scorer_fee',
        'ballboy_fee',
        'referee_fee',
        'medicine_fee',
        'insurance_fee',
    ];

    protected $fillable = [
        'company_id',
        'transfer_fee',
        'yellow_card_fee',
        'red_card_fee',
        'court_fee',
        'medical_fee',
        'scorer_fee',
        'ballboy_fee',
        'referee_fee',
        'medicine_fee',
        'insurance_fee',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_fee' => 'decimal:2',
            'yellow_card_fee' => 'decimal:2',
            'red_card_fee' => 'decimal:2',
            'court_fee' => 'decimal:2',
            'medical_fee' => 'decimal:2',
            'scorer_fee' => 'decimal:2',
            'ballboy_fee' => 'decimal:2',
            'referee_fee' => 'decimal:2',
            'medicine_fee' => 'decimal:2',
            'insurance_fee' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

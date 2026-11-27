<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCheckInSubmission extends Model
{
    use BelongsToCompany;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'confirmed_checkout' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class);
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CheckOutAlertSnooze extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'stay_id', 'snoozed_until'];

    protected function casts(): array
    {
        return ['snoozed_until' => 'datetime'];
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class CheckOutAlertEscalation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'stay_id', 'reason'];
}

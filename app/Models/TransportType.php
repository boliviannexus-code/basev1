<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TransportType extends Model implements Auditable
{
    use AuditsCompanyChanges, HasFactory;

    protected $fillable = [
        'title',
        'description',
    ];
}

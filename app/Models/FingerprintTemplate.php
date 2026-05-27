<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FingerprintTemplate extends Model
{
    protected $fillable = [
        'user_id',
        'template_data',
        'format',
    ];

    protected $hidden = [
        'template_data',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

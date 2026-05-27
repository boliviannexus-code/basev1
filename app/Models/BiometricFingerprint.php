<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricFingerprint extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'finger_position',
        'sample_image',
        'format',
        'quality_score',
        'is_active',
        'enrolled_at',
    ];

    protected $hidden = [
        'sample_image',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'integer',
            'is_active' => 'boolean',
            'enrolled_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

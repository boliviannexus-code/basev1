<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricFingerprint extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'player_id',
        'finger_position',
        'sample_image',
        'template_data',
        'template_format',
        'format',
        'quality_score',
        'is_active',
        'enrolled_at',
    ];

    protected $hidden = [
        'sample_image',
        'template_data',
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

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourItineraryDay extends Model
{
    protected $fillable = [
        'day_number',
        'title',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(TourItineraryStop::class)->orderBy('position');
    }
}

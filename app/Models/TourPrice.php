<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tour_id',
        'title',
        'min_people',
        'max_people',
        'price_usd',
    ];

    protected function casts(): array
    {
        return [
            'min_people' => 'integer',
            'max_people' => 'integer',
            'price_usd' => 'decimal:2',
        ];
    }

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }
}

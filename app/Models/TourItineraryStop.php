<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourItineraryStop extends Model
{
    protected $fillable = [
        'activity_type_id',
        'position',
        'start_time',
        'title',
        'location_name',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'start_time' => 'datetime:H:i',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(TourItineraryDay::class, 'tour_itinerary_day_id');
    }

    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocationCity extends Model
{
    protected $fillable = [
        'geoname_id',
        'name',
        'ascii_name',
        'alternate_names',
        'country_code',
        'country_name',
        'admin1_code',
        'admin1_name',
        'latitude',
        'longitude',
        'population',
        'timezone',
        'feature_code',
        'search_text',
    ];

    protected function casts(): array
    {
        return [
            'geoname_id' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'population' => 'integer',
        ];
    }
}

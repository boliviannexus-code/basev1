<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Court;
use App\Models\Matchday;
use App\Models\MatchdayDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchdayDate>
 */
class MatchdayDateFactory extends Factory
{
    protected $model = MatchdayDate::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'matchday_id' => Matchday::factory(),
            'court_id' => Court::factory(),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'status' => 'draft',
            'notes' => null,
        ];
    }
}

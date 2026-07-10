<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Matchday;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matchday>
 */
class MatchdayFactory extends Factory
{
    protected $model = Matchday::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'season_id' => Season::factory(),
            'number' => fake()->numberBetween(1, 20),
            'name' => null,
            'status' => 'draft',
            'scheduled_date' => null,
            'notes' => null,
        ];
    }
}

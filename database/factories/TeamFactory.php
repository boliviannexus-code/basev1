<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company().' FC';

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'name_normalized' => fn (array $attributes): string => Team::normalizeName($attributes['name']),
            'name_match_key' => fn (array $attributes): string => Team::matchKey($attributes['name']),
            'founded_at' => fake()->dateTimeBetween('-80 years', 'now')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}

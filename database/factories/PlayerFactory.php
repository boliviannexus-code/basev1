<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        $ci = (string) fake()->unique()->numberBetween(1000000, 99999999);

        return [
            'ci' => $ci,
            'ci_normalized' => Player::normalizeCi($ci),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'maternal_name' => fake()->lastName(),
            'internal_code' => fake()->optional()->bothify('PLY-####'),
            'birth_date' => fake()->dateTimeBetween('-35 years', '-12 years')->format('Y-m-d'),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}

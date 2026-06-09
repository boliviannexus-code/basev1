<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReservationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ReservationChannel>
 */
class ReservationChannelFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'company_id' => Company::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'type' => fake()->randomElement(ReservationChannel::TYPES),
            'contact_name' => fake()->optional()->name(),
            'contact_email' => fake()->optional()->safeEmail(),
            'contact_phone' => fake()->optional()->phoneNumber(),
            'commission_percent' => fake()->optional()->randomFloat(2, 0, 25),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'is_protected' => false,
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => fn (): int => User::factory()->create(['company_id' => Company::factory()->create()->id])->id,
            'company_id' => fn (array $attributes): ?int => User::query()->find($attributes['user_id'] ?? null)?->company_id,
            'point_of_sale_id' => null,
            'branch_id' => null,
            'opening_amount' => fake()->randomFloat(2, 0, 500),
            'closing_amount' => null,
            'opened_at' => now(),
            'closed_at' => null,
            'status' => 'open',
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\SpaceCashUserSequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpaceCashUserSequence>
 */
class SpaceCashUserSequenceFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        return [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'receipt_prefix' => 'ESP-'.$user->id,
            'receipt_next_number' => 1,
            'receipt_digits' => 6,
        ];
    }
}

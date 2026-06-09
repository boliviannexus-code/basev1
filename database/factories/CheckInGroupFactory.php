<?php

namespace Database\Factories;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckInGroup>
 */
class CheckInGroupFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory()->create();

        return [
            'company_id' => $company->id,
            'code' => 'CI-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'main_guest_id' => Guest::factory()->create(['company_id' => $company->id])->id,
            'reservation_channel_id' => null,
            'total_people' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'status' => 'checked_in',
            'notes' => null,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Division;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamPlayer>
 */
class TeamPlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'division_id' => Division::factory(),
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'status' => TeamPlayer::STATUS_ACTIVE,
            'joined_at' => now()->toDateString(),
            'ended_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentTeamPlayer>
 */
class TournamentTeamPlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'tournament_id' => Tournament::factory(),
            'tournament_registration_id' => TournamentRegistration::factory(),
            'team_id' => Team::factory(),
            'player_id' => Player::factory(),
            'team_player_id' => TeamPlayer::factory(),
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
            'enabled_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

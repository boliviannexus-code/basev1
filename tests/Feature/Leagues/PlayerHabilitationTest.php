<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlayerHabilitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_habilitations_flow_starts_from_active_tournaments_and_registered_teams(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.view']);
        $team = Team::factory()->create(['company_id' => $company->id, 'name' => 'Club Central']);
        $tournament = $this->tournamentFor($company);
        $this->registerTeam($company, $tournament, $team);

        $this
            ->actingAs($user)
            ->get(route('player-habilitations.index'))
            ->assertOk()
            ->assertSee($tournament->name)
            ->assertSee(route('player-habilitations.teams', $tournament), false);

        $this
            ->actingAs($user)
            ->get(route('player-habilitations.teams', $tournament))
            ->assertOk()
            ->assertSee('CLUB CENTRAL')
            ->assertSee(route('player-habilitations.show', ['tournament' => $tournament, 'team' => $team]), false);
    }

    public function test_habilitation_team_page_shows_roster_and_enabled_players(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.view']);
        $team = Team::factory()->create(['company_id' => $company->id, 'name' => 'Club Central']);
        $tournament = $this->tournamentFor($company);
        $this->registerTeam($company, $tournament, $team);

        $this
            ->actingAs($user)
            ->get(route('player-habilitations.show', ['tournament' => $tournament, 'team' => $team]))
            ->assertOk()
            ->assertSee('Plantilla del club')
            ->assertSee('Habilitados al torneo')
            ->assertSee('CLUB CENTRAL');
    }

    public function test_player_ci_is_unique_by_normalized_value(): void
    {
        [$company, , $user] = $this->leagueUser(['players.create']);

        $this
            ->actingAs($user)
            ->post(route('players.store'), [
                'ci' => '123-ABC',
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'birth_date' => now()->subYears(18)->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect(route('players.index'));

        $this
            ->actingAs($user)
            ->post(route('players.store'), [
                'ci' => '123 abc',
                'first_name' => 'Juan',
                'last_name' => 'Perez',
                'birth_date' => now()->subYears(18)->toDateString(),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('ci');

        $this->assertDatabaseCount('players', 1);
        $player = Player::query()->where('ci_normalized', Player::normalizeCi('123-ABC'))->firstOrFail();

        $this->assertDatabaseHas('players', [
            'ci_normalized' => Player::normalizeCi('123-ABC'),
            'internal_code' => Player::internalCodeForId($player->id),
        ]);
    }

    public function test_existing_player_is_reused_when_affiliating_by_ci(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company);
        $this->registerTeam($company, $tournament, $team);
        $player = Player::factory()->create([
            'ci' => '777ABC',
            'ci_normalized' => Player::normalizeCi('777ABC'),
            'birth_date' => now()->subYears(18)->toDateString(),
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.affiliate'), [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'ci' => '777-abc',
            ])
            ->assertRedirect(route('player-habilitations.show', [
                'tournament' => $tournament,
                'team' => $team,
            ]));

        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseHas('team_players', [
            'company_id' => $company->id,
            'division_id' => $tournament->division_id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('tournament_team_players', [
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
        ]);
    }

    public function test_existing_player_lookup_returns_data_current_team_and_habilitation_status(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $registration = $this->registerTeam($company, $tournament, $team);
        $player = Player::factory()->create([
            'ci' => '777ABC',
            'ci_normalized' => Player::normalizeCi('777ABC'),
            'birth_date' => now()->subYears(18)->toDateString(),
        ]);
        $teamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        TournamentTeamPlayer::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'tournament_registration_id' => $registration->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'team_player_id' => $teamPlayer->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('player-habilitations.player-lookup', [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'ci' => '777-abc',
            ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('player.ci', '777ABC')
            ->assertJsonPath('player.internal_code', Player::internalCodeForId($player->id))
            ->assertJsonPath('current_team.name', $team->name)
            ->assertJsonPath('current_team.is_selected_team', true)
            ->assertJsonPath('status.label', 'Habilitado');
    }

    public function test_existing_player_lookup_returns_only_affiliated_status_when_not_enabled(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $team);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);

        TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('player-habilitations.player-lookup', [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'ci' => $player->ci,
            ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('current_team.name', $team->name)
            ->assertJsonPath('habilitation', null)
            ->assertJsonPath('status.label', 'En plantilla')
            ->assertJsonPath('actions.can_affiliate', true)
            ->assertJsonPath('actions.can_request_transfer', false);
    }

    public function test_existing_player_lookup_allows_transfer_request_when_player_belongs_to_other_team(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $firstTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Alfa']);
        $secondTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Beta']);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $secondTeam);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);

        TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('player-habilitations.player-lookup', [
                'tournament_id' => $tournament->id,
                'team_id' => $secondTeam->id,
                'ci' => $player->ci,
            ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('current_team.name', 'EQUIPO ALFA')
            ->assertJsonPath('current_team.is_selected_team', false)
            ->assertJsonPath('status.label', 'En otro equipo')
            ->assertJsonPath('actions.can_affiliate', false)
            ->assertJsonPath('actions.can_request_transfer', true)
            ->assertJsonPath('actions.is_other_team_roster', true);
    }

    public function test_existing_player_lookup_blocks_transfer_request_when_player_is_enabled_in_same_tournament(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $firstTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Alfa']);
        $secondTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Beta']);
        $tournament = $this->tournamentFor($company, $division);
        $registration = $this->registerTeam($company, $tournament, $firstTeam);
        $this->registerTeam($company, $tournament, $secondTeam);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);
        $teamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
        ]);

        TournamentTeamPlayer::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'tournament_registration_id' => $registration->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
            'team_player_id' => $teamPlayer->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('player-habilitations.player-lookup', [
                'tournament_id' => $tournament->id,
                'team_id' => $secondTeam->id,
                'ci' => $player->ci,
            ]))
            ->assertOk()
            ->assertJsonPath('found', true)
            ->assertJsonPath('status.label', 'Habilitado en otro equipo')
            ->assertJsonPath('actions.can_affiliate', false)
            ->assertJsonPath('actions.can_request_transfer', false)
            ->assertJsonPath('actions.enabled_in_other_team', true)
            ->assertJsonPath('actions.transfer_blocked_reason', 'El jugador ya esta habilitado en este torneo con otro equipo.');
    }

    public function test_player_cannot_be_affiliated_to_two_teams_in_same_league_division(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $firstTeam = Team::factory()->create(['company_id' => $company->id]);
        $secondTeam = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $secondTeam);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);

        TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.affiliate'), [
                'tournament_id' => $tournament->id,
                'team_id' => $secondTeam->id,
                'ci' => $player->ci,
            ])
            ->assertSessionHasErrors('player_id');
    }

    public function test_player_can_be_affiliated_to_another_team_in_another_division(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $firstDivision = Division::factory()->create(['company_id' => $company->id]);
        $secondDivision = Division::factory()->create(['company_id' => $company->id]);
        $firstTeam = Team::factory()->create(['company_id' => $company->id]);
        $secondTeam = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $secondDivision);
        $this->registerTeam($company, $tournament, $secondTeam);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);

        TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $firstDivision->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.affiliate'), [
                'tournament_id' => $tournament->id,
                'team_id' => $secondTeam->id,
                'ci' => $player->ci,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('team_players', [
            'division_id' => $secondDivision->id,
            'team_id' => $secondTeam->id,
            'player_id' => $player->id,
        ]);
        $this->assertDatabaseHas('tournament_team_players', [
            'tournament_id' => $tournament->id,
            'team_id' => $secondTeam->id,
            'player_id' => $player->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
        ]);
    }

    public function test_player_must_be_affiliated_before_tournament_habilitation(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company);
        $this->registerTeam($company, $tournament, $team);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => 999999,
            ])
            ->assertSessionHasErrors('team_player_id');
    }

    public function test_player_age_is_validated_against_tournament_division(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 17]);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $team);
        $player = Player::factory()->create(['birth_date' => now()->subYears(20)->toDateString()]);
        $teamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayer->id,
            ])
            ->assertSessionHasErrors('player_id');
    }

    public function test_player_cannot_be_enabled_twice_in_same_tournament_with_different_teams(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $firstTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Alfa']);
        $secondTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Beta']);
        $tournament = $this->tournamentFor($company, $division);
        $firstRegistration = $this->registerTeam($company, $tournament, $firstTeam);
        $this->registerTeam($company, $tournament, $secondTeam);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);
        $secondTeamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $secondTeam->id,
            'player_id' => $player->id,
        ]);

        TournamentTeamPlayer::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'tournament_registration_id' => $firstRegistration->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
            'team_player_id' => $secondTeamPlayer->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $secondTeamPlayer->id,
            ])
            ->assertSessionHasErrors('team_player_id');

        $this->assertDatabaseCount('tournament_team_players', 1);
    }

    public function test_team_enabled_players_are_limited_by_league_setting_per_category(): void
    {
        Storage::fake('public');

        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        LeagueSetting::query()->create([
            'company_id' => $company->id,
            'max_enabled_players_per_team_category' => 1,
        ]);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $team);
        $players = Player::factory()->count(2)->create(['birth_date' => now()->subYears(18)->toDateString()]);
        $teamPlayers = $players->map(fn (Player $player): TeamPlayer => TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayers[0]->id,
            ])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayers[1]->id,
            ])
            ->assertSessionHasErrors('team_player_id');

        $this->assertDatabaseCount('tournament_team_players', 1);
    }

    public function test_player_can_be_enabled_once_and_disabled(): void
    {
        Storage::fake('public');

        [$company, , $user] = $this->leagueUser(['player-habilitations.create', 'player-habilitations.delete']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $registration = $this->registerTeam($company, $tournament, $team);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);
        $teamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayer->id,
            ])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayer->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('tournament_team_players', 1);

        $habilitation = TournamentTeamPlayer::query()->firstOrFail();

        $this->assertNotNull($habilitation->qr_code_path);
        $this->assertSame(30720, $habilitation->qr_code_size);
        Storage::disk('public')->assertExists($habilitation->qr_code_path);
        $this->assertStringContainsString('<svg', Storage::disk('public')->get($habilitation->qr_code_path));
        $this->assertSame($player->refresh()->qr_code_path, $habilitation->qr_code_path);
        $this->assertSame('player-qrcodes/player-'.$player->id.'.svg', $habilitation->qr_code_path);

        $this
            ->actingAs($user)
            ->delete(route('player-habilitations.destroy', $habilitation), [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
            ])
            ->assertRedirect();

        $this->assertSoftDeleted('tournament_team_players', [
            'id' => $habilitation->id,
            'tournament_registration_id' => $registration->id,
        ]);
    }

    public function test_player_qr_is_reused_when_player_is_reenabled(): void
    {
        Storage::fake('public');

        [$company, , $user] = $this->leagueUser(['player-habilitations.create', 'player-habilitations.delete']);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 25]);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $team);
        $player = Player::factory()->create(['birth_date' => now()->subYears(18)->toDateString()]);
        $teamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayer->id,
            ])
            ->assertRedirect();

        $firstHabilitation = TournamentTeamPlayer::query()->firstOrFail();
        $firstQrPath = $firstHabilitation->qr_code_path;
        $firstQrContents = Storage::disk('public')->get($firstQrPath);

        $this
            ->actingAs($user)
            ->delete(route('player-habilitations.destroy', $firstHabilitation), [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
            ])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.enable'), [
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayer->id,
            ])
            ->assertRedirect();

        $secondHabilitation = TournamentTeamPlayer::query()
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->firstOrFail();

        $this->assertDatabaseCount('tournament_team_players', 2);
        $this->assertSame($firstQrPath, $secondHabilitation->qr_code_path);
        $this->assertSame($firstQrPath, $player->refresh()->qr_code_path);
        $this->assertSame($firstQrContents, Storage::disk('public')->get($secondHabilitation->qr_code_path));
    }

    private function leagueUser(array $permissions): array
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }

    private function tournamentFor(Company $company, ?Division $division = null): Tournament
    {
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division ??= Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 30]);
        $category = DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
        ]);

        return Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
        ]);
    }

    private function registerTeam(Company $company, Tournament $tournament, Team $team): TournamentRegistration
    {
        return TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'team_id' => $team->id,
            'status' => 'registered',
        ]);
    }
}

<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
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
        $player = Player::factory()->create(['ci' => '777ABC', 'ci_normalized' => Player::normalizeCi('777ABC')]);

        $this
            ->actingAs($user)
            ->post(route('player-habilitations.affiliate'), [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'ci' => '777-abc',
            ])
            ->assertRedirect(route('player-habilitations.index', [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
            ]));

        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseHas('team_players', [
            'company_id' => $company->id,
            'division_id' => $tournament->division_id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
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
            ->assertJsonPath('status.label', 'Solo afiliado');
    }

    public function test_player_cannot_be_affiliated_to_two_teams_in_same_league_division(): void
    {
        [$company, , $user] = $this->leagueUser(['player-habilitations.create']);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $firstTeam = Team::factory()->create(['company_id' => $company->id]);
        $secondTeam = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, $division);
        $this->registerTeam($company, $tournament, $secondTeam);
        $player = Player::factory()->create();

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
        $player = Player::factory()->create();

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

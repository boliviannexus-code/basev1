<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Player;
use App\Models\PlayerTransferRequest;
use App\Models\PlayerTransferSetting;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlayerTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_fee_can_be_configured_by_league(): void
    {
        [$company, , $user] = $this->leagueUser(['player-transfers.settings']);

        $this
            ->actingAs($user)
            ->get(route('player-transfers.settings'))
            ->assertOk()
            ->assertSee('Liga Propia')
            ->assertDontSee('Liga Ajena');

        $this
            ->actingAs($user)
            ->post(route('player-transfers.settings.update'), [
                'company_id' => $company->id,
                'fee_amount' => '150.50',
                'next_sequence' => 7,
            ])
            ->assertRedirect(route('player-transfers.settings'));

        $this->assertDatabaseHas('player_transfer_settings', [
            'company_id' => $company->id,
            'fee_amount' => '150.50',
            'next_sequence' => 7,
        ]);
    }

    public function test_transfer_request_generates_sequential_codes_per_league(): void
    {
        $scenario = $this->transferScenario(['player-transfers.create']);

        $this
            ->actingAs($scenario['user'])
            ->postJson(route('player-transfers.store'), [
                'tournament_id' => $scenario['tournament']->id,
                'to_team_id' => $scenario['toTeam']->id,
                'player_id' => $scenario['player']->id,
                'requested_note' => 'Solicitamos el pase para la temporada.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ABCPASE00001');

        $secondPlayer = Player::factory()->create(['company_id' => $scenario['company']->id]);
        TeamPlayer::factory()->create([
            'company_id' => $scenario['company']->id,
            'division_id' => $scenario['division']->id,
            'team_id' => $scenario['fromTeam']->id,
            'player_id' => $secondPlayer->id,
        ]);

        $this
            ->actingAs($scenario['user'])
            ->postJson(route('player-transfers.store'), [
                'tournament_id' => $scenario['tournament']->id,
                'to_team_id' => $scenario['toTeam']->id,
                'player_id' => $secondPlayer->id,
                'requested_note' => 'Segundo pase solicitado.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ABCPASE00002');

        $this->assertDatabaseHas('player_transfer_settings', [
            'company_id' => $scenario['company']->id,
            'next_sequence' => 3,
        ]);
    }

    public function test_pending_transfer_request_cannot_be_duplicated(): void
    {
        $scenario = $this->transferScenario(['player-transfers.create']);

        $payload = [
            'tournament_id' => $scenario['tournament']->id,
            'to_team_id' => $scenario['toTeam']->id,
            'player_id' => $scenario['player']->id,
            'requested_note' => 'Solicitamos el pase para la temporada.',
        ];

        $this->actingAs($scenario['user'])->postJson(route('player-transfers.store'), $payload)->assertCreated();

        $this
            ->actingAs($scenario['user'])
            ->postJson(route('player-transfers.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('data.player_id.0', 'Ya existe una solicitud de pase pendiente para este jugador y equipo.');

        $this->assertDatabaseCount('player_transfer_requests', 1);
    }

    public function test_global_player_from_another_roster_team_can_request_transfer_before_habilitation(): void
    {
        $scenario = $this->transferScenario(['player-transfers.create'], ['player_company_id' => null]);

        $this
            ->actingAs($scenario['user'])
            ->postJson(route('player-transfers.store'), [
                'tournament_id' => $scenario['tournament']->id,
                'to_team_id' => $scenario['toTeam']->id,
                'player_id' => $scenario['player']->id,
                'requested_note' => 'Solicitamos el pase antes de habilitar al jugador.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ABCPASE00001');

        $this->assertDatabaseHas('player_transfer_requests', [
            'company_id' => $scenario['company']->id,
            'division_id' => $scenario['division']->id,
            'from_team_id' => $scenario['fromTeam']->id,
            'to_team_id' => $scenario['toTeam']->id,
            'player_id' => $scenario['player']->id,
            'status' => PlayerTransferRequest::STATUS_PENDING,
        ]);
    }

    public function test_approving_transfer_moves_active_roster_and_records_collection(): void
    {
        $scenario = $this->transferScenario(['player-transfers.create', 'player-transfers.review']);
        $transfer = $this->createTransfer($scenario);

        $this
            ->actingAs($scenario['user'])
            ->patch(route('player-transfers.review', $transfer), [
                'decision' => 'approve',
                'review_notes' => 'Pago verificado.',
            ])
            ->assertRedirect(route('player-transfers.index'));

        $this->assertDatabaseHas('team_players', [
            'id' => $scenario['teamPlayer']->id,
            'status' => TeamPlayer::STATUS_INACTIVE,
        ]);
        $this->assertDatabaseHas('team_players', [
            'company_id' => $scenario['company']->id,
            'division_id' => $scenario['division']->id,
            'team_id' => $scenario['toTeam']->id,
            'player_id' => $scenario['player']->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('player_transfer_requests', [
            'id' => $transfer->id,
            'status' => PlayerTransferRequest::STATUS_APPROVED,
            'collected_amount' => '150.00',
        ]);
    }

    public function test_rejecting_transfer_does_not_move_roster_or_record_collection(): void
    {
        $scenario = $this->transferScenario(['player-transfers.create', 'player-transfers.review']);
        $transfer = $this->createTransfer($scenario);

        $this
            ->actingAs($scenario['user'])
            ->patch(route('player-transfers.review', $transfer), [
                'decision' => 'reject',
                'review_notes' => 'No corresponde el pase.',
            ])
            ->assertRedirect(route('player-transfers.index'));

        $this->assertDatabaseHas('team_players', [
            'id' => $scenario['teamPlayer']->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('team_players', [
            'team_id' => $scenario['toTeam']->id,
            'player_id' => $scenario['player']->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseHas('player_transfer_requests', [
            'id' => $transfer->id,
            'status' => PlayerTransferRequest::STATUS_REJECTED,
            'collected_amount' => null,
        ]);
    }

    private function createTransfer(array $scenario): PlayerTransferRequest
    {
        $this
            ->actingAs($scenario['user'])
            ->postJson(route('player-transfers.store'), [
                'tournament_id' => $scenario['tournament']->id,
                'to_team_id' => $scenario['toTeam']->id,
                'player_id' => $scenario['player']->id,
                'requested_note' => 'Solicitamos el pase para la temporada.',
            ])
            ->assertCreated();

        return PlayerTransferRequest::query()->firstOrFail();
    }

    private function transferScenario(array $permissions, array $overrides = []): array
    {
        [$company, , $user] = $this->leagueUser($permissions);
        $division = Division::factory()->create(['company_id' => $company->id, 'min_age' => 15, 'max_age' => 35]);
        $fromTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Club Origen']);
        $toTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Club Destino']);
        $tournament = $this->tournamentFor($company, $division);
        $player = Player::factory()->create(['company_id' => $overrides['player_company_id'] ?? $company->id]);
        $teamPlayer = TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $fromTeam->id,
            'player_id' => $player->id,
        ]);

        $this->registerTeam($company, $tournament, $toTeam);

        PlayerTransferSetting::query()->create([
            'company_id' => $company->id,
            'fee_amount' => 150,
            'next_sequence' => 1,
        ]);

        return compact('company', 'user', 'division', 'fromTeam', 'toTeam', 'tournament', 'player', 'teamPlayer');
    }

    private function tournamentFor(Company $company, Division $division): Tournament
    {
        $season = Season::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
        ]);

        return Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);
    }

    private function registerTeam(Company $company, Tournament $tournament, Team $team): TournamentRegistration
    {
        return TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $team->id,
            'status' => 'registered',
        ]);
    }

    private function leagueUser(array $permissions): array
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $company = Company::factory()->create(['name' => 'Liga Propia', 'code' => 'ABC']);
        $otherCompany = Company::factory()->create(['name' => 'Liga Ajena', 'code' => 'XYZ']);
        $user = User::factory()->for($company)->create();
        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }
}

<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Team;
use App\Models\TeamUpdateRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_user_can_register_team_in_own_league_with_default_foundation_date(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['teams.view', 'teams.create']);

        $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'company_id' => $otherCompany->id,
                'name' => 'club deportivo central',
                'notes' => 'Equipo principal',
                'is_active' => '1',
            ])
            ->assertRedirect(route('teams.index'));

        $this->assertDatabaseHas('teams', [
            'company_id' => $company->id,
            'name' => 'CLUB DEPORTIVO CENTRAL',
            'founded_at' => now()->toDateString(),
        ]);
    }

    public function test_team_name_cannot_repeat_in_same_league_but_can_exist_in_another_league(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['teams.create']);
        Team::factory()->create(['company_id' => $company->id, 'name' => 'Real Norte']);
        Team::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Real Norte']);

        $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => 'Real Norte',
                'founded_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('teams', 2);
    }

    public function test_team_name_is_saved_uppercase(): void
    {
        [$company, , $user] = $this->leagueUser(['teams.create']);

        $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => '  club   deportivo central  ',
                'founded_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertRedirect(route('teams.index'));

        $this->assertDatabaseHas('teams', [
            'company_id' => $company->id,
            'name' => 'CLUB DEPORTIVO CENTRAL',
            'name_normalized' => Team::normalizeName('CLUB DEPORTIVO CENTRAL'),
        ]);
    }

    public function test_team_name_cannot_repeat_with_different_case_or_extra_spaces(): void
    {
        [$company, , $user] = $this->leagueUser(['teams.create']);
        Team::factory()->create([
            'company_id' => $company->id,
            'name' => 'Nacional Achachicala',
            'name_normalized' => Team::normalizeName('Nacional Achachicala'),
        ]);

        $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => ' nacional   achachicala ',
                'founded_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_team_name_blocks_common_football_word_matches(): void
    {
        [$company, , $user] = $this->leagueUser(['teams.create']);
        Team::factory()->create(['company_id' => $company->id, 'name' => 'MACANUDOS FC']);

        $this
            ->actingAs($user)
            ->post(route('teams.store'), [
                'name' => 'MACANUDOS',
                'founded_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('teams', 1);
    }

    public function test_team_edit_blocks_common_football_word_matches(): void
    {
        [$company, , $user] = $this->leagueUser(['teams.update']);
        Team::factory()->create(['company_id' => $company->id, 'name' => 'MACANUDOS FC']);
        $team = Team::factory()->create(['company_id' => $company->id, 'name' => 'LOS AMIGOS']);

        $this
            ->actingAs($user)
            ->put(route('teams.update', $team), [
                'name' => 'MACANUDOS',
                'founded_at' => now()->toDateString(),
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_team_name_matches_are_scoped_to_active_league(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['teams.view']);
        Team::factory()->create(['company_id' => $company->id, 'name' => 'Academia La Paz']);
        Team::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Academia Ajena']);

        $this
            ->actingAs($user)
            ->getJson(route('teams.matches', ['name' => 'Academia']))
            ->assertOk()
            ->assertSee('ACADEMIA LA PAZ')
            ->assertDontSee('ACADEMIA AJENA');
    }

    public function test_team_edit_by_league_user_requires_superadmin_approval(): void
    {
        [$company, , $user] = $this->leagueUser(['teams.update']);
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original FC',
            'founded_at' => '2020-01-01',
        ]);

        $this
            ->actingAs($user)
            ->put(route('teams.update', $team), [
                'name' => 'Nuevo FC',
                'founded_at' => '2021-02-03',
                'notes' => 'Cambio solicitado',
                'is_active' => '1',
            ])
            ->assertRedirect(route('teams.index'));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'ORIGINAL FC',
            'founded_at' => '2020-01-01',
        ]);
        $this->assertDatabaseHas('team_update_requests', [
            'team_id' => $team->id,
            'requested_by' => $user->id,
            'status' => TeamUpdateRequest::STATUS_PENDING,
        ]);

        $this->assertSame('NUEVO FC', TeamUpdateRequest::query()->firstOrFail()->proposed_data['name']);
    }

    public function test_superadmin_can_approve_team_edit_request(): void
    {
        [$company, , $user] = $this->leagueUser(['teams.update']);
        $superadmin = $this->superadmin(['teams.approve-updates']);
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original FC',
            'founded_at' => '2020-01-01',
        ]);

        $this->actingAs($user)->put(route('teams.update', $team), [
            'name' => 'Nuevo FC',
            'founded_at' => '2021-02-03',
            'notes' => 'Cambio aprobado',
            'is_active' => '1',
        ]);

        $request = TeamUpdateRequest::query()->firstOrFail();

        $this
            ->actingAs($superadmin)
            ->patch(route('teams.approvals.review', $request), [
                'decision' => 'approve',
                'review_notes' => 'Ok',
            ])
            ->assertRedirect(route('teams.approvals'));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'NUEVO FC',
            'founded_at' => '2021-02-03',
        ]);
        $this->assertDatabaseHas('team_update_requests', [
            'id' => $request->id,
            'status' => TeamUpdateRequest::STATUS_APPROVED,
            'approved_by' => $superadmin->id,
        ]);
    }

    public function test_superadmin_editing_team_still_creates_approval_request(): void
    {
        [$company] = $this->leagueUser([]);
        $superadmin = $this->superadmin(['teams.update', 'teams.approve-updates']);
        $team = Team::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original FC',
            'founded_at' => '2020-01-01',
        ]);

        $this
            ->actingAs($superadmin)
            ->put(route('teams.update', $team), [
                'name' => 'Cambio Superadmin',
                'founded_at' => '2022-04-05',
                'notes' => null,
                'is_active' => '1',
            ])
            ->assertRedirect(route('teams.index'));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'ORIGINAL FC',
        ]);
        $this->assertDatabaseHas('team_update_requests', [
            'team_id' => $team->id,
            'requested_by' => $superadmin->id,
            'status' => TeamUpdateRequest::STATUS_PENDING,
        ]);
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

    private function superadmin(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create(['company_id' => null]);
        Role::findOrCreate('super_admin')->givePermissionTo($permissions);
        $user->assignRole('super_admin');

        return $user;
    }
}

<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Player;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlayerTeamDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_datatable_searches_and_filters_by_status(): void
    {
        [, , $user] = $this->leagueUser(['players.view']);

        Player::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Mamani', 'ci' => '777ABC', 'ci_normalized' => Player::normalizeCi('777ABC'), 'is_active' => true]);
        Player::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Quispe', 'ci' => '888ABC', 'ci_normalized' => Player::normalizeCi('888ABC'), 'is_active' => false]);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.players', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => '777'],
                'columns' => $this->playerColumns(),
                'is_active' => '1',
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonFragment(['ci' => '777ABC']);
    }

    public function test_teams_datatable_is_scoped_to_user_company_and_searchable(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['teams.view']);

        Team::factory()->create(['company_id' => $company->id, 'name' => 'Atletico Mejillones', 'name_normalized' => Team::normalizeName('Atletico Mejillones')]);
        Team::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Atletico Ajeno', 'name_normalized' => Team::normalizeName('Atletico Ajeno')]);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.teams', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'Atletico'],
                'columns' => $this->teamColumns(),
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonFragment(['company_name' => $company->name])
            ->assertJsonMissing(['company_name' => $otherCompany->name]);
    }

    public function test_players_datatable_searches_by_first_name(): void
    {
        [, , $user] = $this->leagueUser(['players.view']);

        Player::factory()->create(['first_name' => 'Boris', 'last_name' => 'Aliaga', 'ci' => '111AAA', 'ci_normalized' => Player::normalizeCi('111AAA')]);
        Player::factory()->create(['first_name' => 'Carlos', 'last_name' => 'Mamani', 'ci' => '222AAA', 'ci_normalized' => Player::normalizeCi('222AAA')]);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.players', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'boris'],
                'columns' => $this->playerColumns(),
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertSee('Boris', false)
            ->assertDontSee('Carlos', false);
    }

    public function test_players_datatable_searches_across_full_name(): void
    {
        [, , $user] = $this->leagueUser(['players.view']);

        Player::factory()->create([
            'first_name' => 'Boris Wilson',
            'last_name' => 'Pacheco Rodriguez',
            'ci' => '333AAA',
            'ci_normalized' => Player::normalizeCi('333AAA'),
        ]);
        Player::factory()->create([
            'first_name' => 'Wilson',
            'last_name' => 'Mamani',
            'ci' => '444AAA',
            'ci_normalized' => Player::normalizeCi('444AAA'),
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.players', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'wilson pacheco'],
                'columns' => $this->playerColumns(),
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertSee('Boris Wilson', false)
            ->assertDontSee('Wilson Mamani', false);
    }

    public function test_players_datatable_searches_non_contiguous_name_terms(): void
    {
        [, , $user] = $this->leagueUser(['players.view']);

        Player::factory()->create([
            'first_name' => 'Rene Esteban',
            'last_name' => 'Pacheco Mamani',
            'ci' => '555AAA',
            'ci_normalized' => Player::normalizeCi('555AAA'),
        ]);
        Player::factory()->create([
            'first_name' => 'Rene',
            'last_name' => 'Quispe',
            'ci' => '666AAA',
            'ci_normalized' => Player::normalizeCi('666AAA'),
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.players', [
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'search' => ['value' => 'rene pacheco'],
                'columns' => $this->playerColumns(),
            ]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertSee('Rene Esteban', false)
            ->assertDontSee('Rene Quispe', false);
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

    private function playerColumns(): array
    {
        return [
            ['data' => 'full_name', 'name' => 'players.last_name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'ci', 'name' => 'players.ci_normalized', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'internal_code', 'name' => 'players.internal_code', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'birth_date', 'name' => 'players.birth_date', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'is_active', 'name' => 'players.is_active', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '']],
            ['data' => 'actions', 'name' => 'actions', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '']],
        ];
    }

    private function teamColumns(): array
    {
        return [
            ['data' => 'team_name', 'name' => 'teams.name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'founded_at', 'name' => 'teams.founded_at', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'company_name', 'name' => 'companies.name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '']],
            ['data' => 'is_active', 'name' => 'teams.is_active', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '']],
            ['data' => 'actions', 'name' => 'actions', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '']],
        ];
    }
}

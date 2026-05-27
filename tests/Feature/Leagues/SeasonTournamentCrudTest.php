<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SeasonTournamentCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_user_can_manage_own_seasons(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['seasons.view', 'seasons.create']);

        Season::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Gestion ajena']);

        $this
            ->actingAs($user)
            ->post(route('seasons.store'), [
                'company_id' => $otherCompany->id,
                'name' => 'Gestion 2026',
                'year' => 2026,
                'status' => 'active',
                'is_active' => '1',
            ])
            ->assertRedirect(route('seasons.index'));

        $this->assertDatabaseHas('seasons', [
            'company_id' => $company->id,
            'name' => 'Gestion 2026',
        ]);

        $this
            ->actingAs($user)
            ->get(route('seasons.index'))
            ->assertOk()
            ->assertSee('Gestion 2026')
            ->assertDontSee('Gestion ajena');
    }

    public function test_tournament_must_use_season_from_active_league(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);
        $ownSeason = Season::factory()->create(['company_id' => $company->id, 'name' => 'Gestion propia']);
        $otherSeason = Season::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Gestion ajena']);
        $ownDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera Division']);
        $otherDivision = Division::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Division ajena']);
        $ownCategory = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $ownDivision->id, 'name' => 'Sub 17']);
        $otherCategory = DivisionCategory::factory()->create(['company_id' => $otherCompany->id, 'division_id' => $otherDivision->id, 'name' => 'Sub 20']);

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $otherSeason->id,
                'division_id' => $ownDivision->id,
                'category_id' => $ownCategory->id,
                'name' => 'Torneo filtrado',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('season_id');

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $ownSeason->id,
                'division_id' => $otherDivision->id,
                'category_id' => $otherCategory->id,
                'name' => 'Torneo division filtrada',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('division_id');

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $ownSeason->id,
                'division_id' => $ownDivision->id,
                'category_id' => $ownCategory->id,
                'name' => 'Torneo Apertura 2026',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertRedirect(route('tournaments.index'));

        $this->assertDatabaseHas('tournaments', [
            'company_id' => $company->id,
            'season_id' => $ownSeason->id,
            'division_id' => $ownDivision->id,
            'category_id' => $ownCategory->id,
            'name' => 'Primera Division - Sub 17 - Gestion propia',
        ]);
    }

    public function test_tournament_name_is_generated_from_division_category_and_season(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);
        $season = Season::factory()->create(['company_id' => $company->id, 'name' => 'Gestion 2026']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Juvenil']);
        $category = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id, 'name' => 'Sub 17']);

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $season->id,
                'division_id' => $division->id,
                'category_id' => $category->id,
                'name' => 'Nombre manual ignorado',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertRedirect(route('tournaments.index'));

        $this->assertDatabaseHas('tournaments', [
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'name' => 'Juvenil - Sub 17 - Gestion 2026',
        ]);
    }

    public function test_tournament_category_must_belong_to_selected_division_and_league(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera']);
        $secondDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Juvenil']);
        $otherDivision = Division::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Ajena']);
        $wrongDivisionCategory = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $secondDivision->id]);
        $otherLeagueCategory = DivisionCategory::factory()->create(['company_id' => $otherCompany->id, 'division_id' => $otherDivision->id]);

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $season->id,
                'division_id' => $division->id,
                'category_id' => $wrongDivisionCategory->id,
                'name' => 'Torneo categoria incorrecta',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_id');

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $season->id,
                'division_id' => $division->id,
                'category_id' => $otherLeagueCategory->id,
                'name' => 'Torneo categoria ajena',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_tournament_category_cannot_repeat_in_same_season(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id]);

        Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'name' => 'Torneo Apertura',
        ]);

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $season->id,
                'division_id' => $division->id,
                'category_id' => $category->id,
                'name' => 'Torneo Clausura',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_same_category_can_be_used_in_different_seasons(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);
        $firstSeason = Season::factory()->create(['company_id' => $company->id, 'name' => 'Gestion 2026']);
        $secondSeason = Season::factory()->create(['company_id' => $company->id, 'name' => 'Gestion 2027']);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id]);

        Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $firstSeason->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'name' => 'Torneo 2026',
        ]);

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $secondSeason->id,
                'division_id' => $division->id,
                'category_id' => $category->id,
                'name' => 'Torneo 2027',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertRedirect(route('tournaments.index'));
    }

    public function test_tournament_requires_real_division_and_category_values(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);
        $season = Season::factory()->create(['company_id' => $company->id]);

        $this
            ->actingAs($user)
            ->post(route('tournaments.store'), [
                'season_id' => $season->id,
                'division_id' => '',
                'category_id' => '',
                'name' => 'Torneo sin datos reales',
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors(['division_id', 'category_id']);
    }

    public function test_legacy_tournament_without_category_loads_edit_form_and_requires_category_on_update(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view', 'tournaments.update']);
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $tournament = Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => null,
        ]);

        $this
            ->actingAs($user)
            ->get(route('tournaments.edit', $tournament))
            ->assertOk()
            ->assertSee('name="category_id"', false);

        $this
            ->actingAs($user)
            ->put(route('tournaments.update', $tournament), [
                'season_id' => $season->id,
                'division_id' => $division->id,
                'category_id' => '',
                'name' => $tournament->name,
                'status' => 'planned',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_id');
    }

    public function test_league_user_does_not_see_other_league_tournaments(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournaments.view']);
        $ownSeason = Season::factory()->create(['company_id' => $company->id]);
        $otherSeason = Season::factory()->create(['company_id' => $otherCompany->id]);
        $ownDivision = Division::factory()->create(['company_id' => $company->id]);
        $otherDivision = Division::factory()->create(['company_id' => $otherCompany->id]);

        Tournament::factory()->create(['company_id' => $company->id, 'season_id' => $ownSeason->id, 'division_id' => $ownDivision->id, 'name' => 'Torneo propio']);
        Tournament::factory()->create(['company_id' => $otherCompany->id, 'season_id' => $otherSeason->id, 'division_id' => $otherDivision->id, 'name' => 'Torneo ajeno']);

        $this
            ->actingAs($user)
            ->get(route('tournaments.index'))
            ->assertOk()
            ->assertSee('Torneo propio')
            ->assertDontSee('Torneo ajeno');
    }

    public function test_tournament_form_requests_division_with_age_range(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view', 'tournaments.create']);

        Season::factory()->create(['company_id' => $company->id, 'name' => 'Gestion 2026']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Sub 17', 'min_age' => 15, 'max_age' => 17]);
        DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id, 'name' => 'Categoria A']);

        $this
            ->actingAs($user)
            ->get(route('tournaments.create'))
            ->assertOk()
            ->assertSee('Gestion 2026')
            ->assertSee('name="division_id"', false)
            ->assertSee('name="category_id"', false)
            ->assertSee('Sub 17')
            ->assertSee('Categoria A')
            ->assertSee('15 a 17 años');
    }

    public function test_tournament_index_and_show_display_category(): void
    {
        [$company, , $user] = $this->leagueUser(['tournaments.view']);
        $season = Season::factory()->create(['company_id' => $company->id, 'name' => 'Gestion 2026']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera']);
        $category = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id, 'name' => 'Sub 17']);
        $tournament = Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'name' => 'Torneo Visible',
        ]);

        $this
            ->actingAs($user)
            ->get(route('tournaments.index'))
            ->assertOk()
            ->assertSee('Torneo Visible')
            ->assertSee('Sub 17');

        $this
            ->actingAs($user)
            ->get(route('tournaments.show', $tournament))
            ->assertOk()
            ->assertSee('Categoria')
            ->assertSee('Sub 17');
    }

    public function test_league_user_can_create_division_with_age_range(): void
    {
        [$company, , $user] = $this->leagueUser(['divisions.view', 'divisions.create']);

        $this
            ->actingAs($user)
            ->post(route('divisions.store'), [
                'name' => 'Sub 20',
                'min_age' => 18,
                'max_age' => 20,
                'description' => 'Jugadores Sub 20',
                'is_active' => '1',
            ])
            ->assertRedirect(route('divisions.index'));

        $this->assertDatabaseHas('divisions', [
            'company_id' => $company->id,
            'name' => 'Sub 20',
            'min_age' => 18,
            'max_age' => 20,
        ]);
    }

    public function test_league_user_can_manage_existing_categories_from_division_edit(): void
    {
        [$company, , $user] = $this->leagueUser(['divisions.view', 'divisions.update']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera']);
        $otherDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Juvenil']);
        $currentCategory = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id, 'name' => 'Sub 17']);
        $categoryToAdd = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $otherDivision->id, 'name' => 'Sub 20']);

        $this
            ->actingAs($user)
            ->put(route('divisions.update', $division), [
                'name' => 'Primera',
                'min_age' => $division->min_age,
                'max_age' => $division->max_age,
                'description' => $division->description,
                'sync_categories' => '1',
                'category_ids' => [$categoryToAdd->id],
                'is_active' => '1',
            ])
            ->assertRedirect(route('divisions.index'));

        $this->assertDatabaseHas('division_categories', [
            'id' => $categoryToAdd->id,
            'division_id' => $division->id,
            'deleted_at' => null,
        ]);

        $this->assertSoftDeleted('division_categories', [
            'id' => $currentCategory->id,
        ]);
    }

    public function test_division_edit_cannot_remove_category_used_by_tournament(): void
    {
        [$company, , $user] = $this->leagueUser(['divisions.view', 'divisions.update']);
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id]);
        Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
        ]);

        $this
            ->actingAs($user)
            ->put(route('divisions.update', $division), [
                'name' => $division->name,
                'min_age' => $division->min_age,
                'max_age' => $division->max_age,
                'sync_categories' => '1',
                'category_ids' => [],
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('category_ids');
    }

    public function test_division_edit_form_lists_existing_categories(): void
    {
        [$company, , $user] = $this->leagueUser(['divisions.view', 'divisions.update']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera']);
        DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id, 'name' => 'Sub 17']);

        $this
            ->actingAs($user)
            ->get(route('divisions.edit', $division))
            ->assertOk()
            ->assertSee('name="category_ids[]"', false)
            ->assertSee('Sub 17');
    }

    public function test_sidebar_shows_divisions_menu_for_authorized_user(): void
    {
        [, , $user] = $this->leagueUser(['dashboard.view', 'divisions.view']);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Divisiones')
            ->assertSee(route('divisions.index'), false);
    }

    public function test_league_user_can_create_category_for_own_division(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['categories.view', 'categories.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera Division']);
        $otherDivision = Division::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Division ajena']);

        $this
            ->actingAs($user)
            ->post(route('categories.store'), [
                'division_id' => $otherDivision->id,
                'name' => 'Sub 17',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('division_id');

        $this
            ->actingAs($user)
            ->post(route('categories.store'), [
                'division_id' => $division->id,
                'name' => 'Sub 17',
                'description' => 'Categoria Sub 17',
                'is_active' => '1',
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertDatabaseHas('division_categories', [
            'company_id' => $company->id,
            'division_id' => $division->id,
            'name' => 'Sub 17',
        ]);
    }

    public function test_category_name_cannot_repeat_in_same_division(): void
    {
        [$company, , $user] = $this->leagueUser(['categories.view', 'categories.create']);
        $division = Division::factory()->create(['company_id' => $company->id]);

        DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'name' => 'Sub 17',
        ]);

        $this
            ->actingAs($user)
            ->post(route('categories.store'), [
                'division_id' => $division->id,
                'name' => 'sub 17',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_same_category_name_can_exist_in_different_divisions(): void
    {
        [$company, , $user] = $this->leagueUser(['categories.view', 'categories.create']);
        $firstDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera']);
        $secondDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Juvenil']);

        DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $firstDivision->id,
            'name' => 'Sub 17',
        ]);

        $this
            ->actingAs($user)
            ->post(route('categories.store'), [
                'division_id' => $secondDivision->id,
                'name' => 'Sub 17',
                'is_active' => '1',
            ])
            ->assertRedirect(route('categories.index'));

        $this->assertSame(2, DivisionCategory::query()->where('name', 'Sub 17')->count());
    }

    private function leagueUser(array $permissions): array
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $company = Company::factory()->create(['name' => 'Liga Propia']);
        $otherCompany = Company::factory()->create(['name' => 'Liga Ajena']);
        $user = User::factory()->for($company)->create();
        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }
}

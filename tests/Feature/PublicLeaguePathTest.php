<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLeaguePathTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_league_is_listed_and_accessible_by_path(): void
    {
        $company = Company::factory()->create([
            'name' => 'Liga Ejemplo', 'subdomain' => 'ligax', 'public_page_is_enabled' => true,
        ]);

        $this->get('/')->assertOk()->assertSee('Liga Ejemplo')->assertSee('/ligax');
        $this->get('/ligax')->assertOk()->assertSee($company->name)->assertSee('/ligax/partidos');
        $this->get('/ligax/partidos')->assertOk();
        $this->get('/ligax/tabla-posiciones')->assertOk();
        $this->get('/ligax/kardex')->assertOk();
    }

    public function test_disabled_or_unknown_leagues_are_not_public(): void
    {
        Company::factory()->create(['subdomain' => 'privada', 'public_page_is_enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('/privada');
        $this->get('/privada')->assertNotFound();
        $this->get('/inexistente')->assertNotFound();
    }
}

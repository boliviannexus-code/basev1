<?php

namespace Tests\Feature\Countries;

use App\Models\Company;
use App\Models\Country;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CountryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_view_seeded_countries_and_feature_one(): void
    {
        $user = $this->companyUser();

        $this
            ->actingAs($user)
            ->get(route('countries.index'))
            ->assertOk()
            ->assertSee('Bolivia')
            ->assertSee('Destacado');

        $argentina = Country::query()
            ->where('company_id', $user->company_id)
            ->where('iso_code', 'AR')
            ->firstOrFail();

        $this
            ->actingAs($user)
            ->patch(route('countries.feature', $argentina))
            ->assertRedirect();

        $this->assertTrue($argentina->refresh()->is_featured);
    }

    public function test_company_user_cannot_manage_other_company_country(): void
    {
        $user = $this->companyUser();
        $otherCompany = Company::factory()->create();
        Country::factory()->create([
            'company_id' => $otherCompany->id,
            'iso_code' => 'ZZ',
            'name' => 'Pais externo',
        ]);

        $otherCountry = Country::query()
            ->withoutGlobalScopes()
            ->where('company_id', $otherCompany->id)
            ->firstOrFail();

        $this
            ->actingAs($user)
            ->patch(route('countries.feature', $otherCountry))
            ->assertNotFound();
    }

    private function companyUser(): User
    {
        Permission::findOrCreate('countries.manage');

        $user = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);
        $user->givePermissionTo('countries.manage');

        return $user;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OnlineCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_is_public_company_specific_and_translated(): void
    {
        $company = Company::factory()->create(['name' => 'Hotel Andino']);
        $other = Company::factory()->create(['name' => 'Hotel Sur']);

        $this->get(route('public.online-check-in.welcome', $company))->assertOk()
            ->assertSee('Hotel Andino')->assertSee('¡Bienvenido!')->assertSee('Tengo una reserva')->assertSee('No tengo una reserva')->assertDontSee('Hotel Sur');
        $this->get(route('public.online-check-in.welcome', ['company' => $other, 'lang' => 'en']))->assertOk()
            ->assertSee('Hotel Sur')->assertSee('Welcome!')->assertSee('I have a reservation');
        $this->get(route('public.online-check-in.welcome', ['company' => $company, 'lang' => 'pt', 'choice' => 'new']))->assertOk()
            ->assertSee('Esta opção estará disponível em breve.')->assertSee('name="lang"', false);
        $company->update(['is_active' => false]);
        $this->get(route('public.online-check-in.welcome', $company))->assertNotFound();
    }

    public function test_qr_requires_permission_and_company_access(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('companies.view');
        $this->withoutVite();
        $this->actingAs($user)->get(route('companies.online-check-in.qr', $company))->assertForbidden();
        $user->givePermissionTo('companies.view');
        $this->get(route('companies.online-check-in.qr', $company))->assertOk()
            ->assertSee(route('public.online-check-in.welcome', $company))->assertSee('data:image/svg+xml;base64,', false);
        $this->get(route('companies.online-check-in.qr', $other))->assertNotFound();
    }
}

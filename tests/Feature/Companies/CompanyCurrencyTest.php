<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyCurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_admin_can_change_company_currency(): void
    {
        Permission::findOrCreate('companies.update');
        $company = Company::factory()->create(['name' => 'Hotel']);
        $this->assertSame('BOB', $company->refresh()->local_currency);
        $editor = User::factory()->create(['company_id' => $company->id]);
        $editor->givePermissionTo('companies.update');
        $this->actingAs($editor)->put(route('companies.update', $company), ['name' => 'Hotel', 'local_currency' => 'PEN'])->assertSessionHasErrors('local_currency');
        $this->assertSame('BOB', $company->refresh()->local_currency);
        $admin = User::factory()->create(['company_id' => null]);
        Role::findOrCreate('super_admin')->givePermissionTo('companies.update');
        $admin->assignRole('super_admin');
        $this->actingAs($admin)->put(route('companies.update', $company), ['name' => 'Hotel', 'local_currency' => 'PEN'])->assertSessionHasNoErrors();
        $this->assertSame('PEN', $company->refresh()->local_currency);
        $this->put(route('companies.update', $company), ['name' => 'Hotel', 'local_currency' => 'USD'])->assertSessionHasErrors('local_currency');
    }

    public function test_component_uses_owner_company_and_keeps_usd_and_values(): void
    {
        $peru = Company::factory()->create(['local_currency' => 'PEN']);
        $argentina = Company::factory()->create(['local_currency' => 'ARS']);
        $user = User::factory()->create(['company_id' => $argentina->id]);
        $this->actingAs($user);
        $html = Blade::render('<x-ui.money :amount="100" currency="BOB" :company="$company" :exchange-rate="2" />', ['company' => $peru]);
        $this->assertStringContainsString('100.00 PEN', $html);
        $this->assertStringContainsString('50.00 USD', $html);
        $html = Blade::render('<x-ui.money :amount="100" currency="USD" :company="$company" :exchange-rate="2" />', ['company' => $peru]);
        $this->assertStringContainsString('100.00 USD', $html);
        $this->assertStringContainsString('200.00 PEN', $html);
        $this->assertSame('$ ARS', company_currency_symbol($argentina));
    }
}

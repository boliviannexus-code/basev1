<?php

namespace Tests\Feature\ExchangeRates;

use App\Models\Company;
use App\Models\ExchangeRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ExchangeRateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_update_current_exchange_rate(): void
    {
        $user = $this->companyUser();

        ExchangeRate::query()->create([
            'company_id' => $user->company_id,
            'from_currency' => 'USD',
            'to_currency' => 'BOB',
            'rate' => 6.9000,
            'effective_date' => '2026-06-06',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('exchange-rates.store'), [
                'rate' => 6.9600,
                'effective_date' => '2026-06-07',
                'notes' => 'Actualizacion diaria',
            ])
            ->assertRedirect(route('exchange-rates.index'));

        $this->assertDatabaseHas('exchange_rates', [
            'company_id' => $user->company_id,
            'rate' => 6.9600,
            'is_active' => true,
        ]);

        $this->assertSame(1, ExchangeRate::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->count());
    }

    public function test_current_rate_is_company_scoped(): void
    {
        $user = $this->companyUser();
        $otherCompany = Company::factory()->create();

        ExchangeRate::query()->create([
            'company_id' => $user->company_id,
            'from_currency' => 'USD',
            'to_currency' => 'BOB',
            'rate' => 6.9600,
            'effective_date' => '2026-06-07',
            'is_active' => true,
        ]);

        ExchangeRate::query()->create([
            'company_id' => $otherCompany->id,
            'from_currency' => 'USD',
            'to_currency' => 'BOB',
            'rate' => 7.2000,
            'effective_date' => '2026-06-07',
            'is_active' => true,
        ]);

        $this->assertSame(6.96, ExchangeRate::currentRateForCompany((int) $user->company_id));
        $this->assertSame(7.2, ExchangeRate::currentRateForCompany((int) $otherCompany->id));
    }

    private function companyUser(): User
    {
        Permission::findOrCreate('exchange-rates.manage');

        $user = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);
        $user->givePermissionTo('exchange-rates.manage');

        return $user;
    }
}

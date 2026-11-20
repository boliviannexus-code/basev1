<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class BasicReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_view_company_scoped_reports(): void
    {
        Permission::findOrCreate('reports.view');
        Permission::findOrCreate('reports.print');

        [$company, $user, $method, $register] = $this->context('Efectivo propio');
        [$otherCompany, $otherUser, $otherMethod, $otherRegister] = $this->context('Efectivo ajeno');
        $user->givePermissionTo(['reports.view', 'reports.print']);

        $this->createIncome($company, $user, $method, $register, 'ING-OWN-001', 25);
        $this->createIncome($otherCompany, $otherUser, $otherMethod, $otherRegister, 'ING-OTHER-001', 99);
        $this->withoutVite();

        $query = [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ];

        $this->actingAs($user)
            ->get(route('reports.index', $query))
            ->assertOk()
            ->assertSee('Resumen por tipo de pago')
            ->assertSee('Efectivo propio')
            ->assertDontSee('Efectivo ajeno')
            ->assertDontSee('Ventas POS');

        $this->actingAs($user)
            ->get(route('reports.print', $query))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_occupancy_opens_on_today_and_general_remains_available(): void
    {
        Permission::findOrCreate('reports.view');
        Permission::findOrCreate('reports.print');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['reports.view', 'reports.print']);
        $this->travelTo(\Carbon\Carbon::parse('2026-11-18 10:00:00'));
        $this->withoutVite();

        $this->actingAs($user)->get(route('reports.occupancy.index'))
            ->assertOk()->assertViewHas('isDaily', true)->assertSee('2026-11-18')->assertDontSee('occupancy-user');
        $this->get(route('reports.occupancy.index', ['user_id' => 999999]))
            ->assertOk()->assertViewHas('filters', fn (array $filters): bool => $filters['user_id'] === null);
        $this->get(route('reports.occupancy.index', ['daily' => 0]))
            ->assertOk()->assertViewHas('isDaily', false)->assertSee('Resumen por hotel');
        $this->get(route('reports.occupancy.print'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_user_without_reports_permission_cannot_view_reports(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()]);

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertForbidden();
    }

    /**
     * @return array{0: Company, 1: User, 2: PaymentMethod, 3: SpaceCashRegister}
     */
    private function context(string $methodName): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $method = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => $methodName,
            'is_active' => true,
        ]);
        $register = SpaceCashRegister::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return [$company, $user, $method, $register];
    }

    private function createIncome(
        Company $company,
        User $user,
        PaymentMethod $method,
        SpaceCashRegister $register,
        string $receipt,
        float $amount,
    ): void {
        SpaceCashIncome::query()->create([
            'company_id' => $company->id,
            'space_cash_register_id' => $register->id,
            'user_id' => $user->id,
            'payment_method_id' => $method->id,
            'receipt_number' => $receipt,
            'detail' => 'Ingreso directo',
            'quantity' => 1,
            'amount' => $amount,
            'received_at' => now(),
        ]);
    }
}

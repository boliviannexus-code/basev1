<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\AccountStatement;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Space;
use App\Models\SpaceCashLodgingPayment;
use App\Models\SpaceCashRegister;
use App\Models\Stay;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

final class OperationalEconomicReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_filters_lodging_income_by_space_and_groups_payment_methods(): void
    {
        [$company, $user, $method, $register] = $this->context();
        $selectedSpace = Space::factory()->create(['company_id' => $company->id, 'title' => 'Casa Norte']);
        $otherSpace = Space::factory()->create(['company_id' => $company->id, 'title' => 'Casa Sur']);

        $this->createLodgingPayment($company, $user, $method, $register, $selectedSpace, 'HOS-000001', 80);
        $this->createLodgingPayment($company, $user, $method, $register, $otherSpace, 'HOS-000002', 120);

        $reports = app(OperationalReportService::class);
        $filters = $reports->filters([
            'space_id' => $selectedSpace->id,
            'from' => now()->subDay()->toDateString(),
            'to' => now()->addDay()->toDateString(),
        ]);
        $report = $reports->build((int) $company->id, $filters);

        $this->assertCount(1, $report['lodging']);
        $this->assertSame('HOS-000001', $report['lodging']->first()->receipt_number);
        $this->assertSame(80.0, $report['totals']['lodging']);
        $this->assertSame('Efectivo', $report['methodSummary']->first()['name']);
        $this->assertSame(80.0, $report['methodSummary']->first()['income']);
        $this->assertArrayNotHasKey('sales', $report);
        $this->assertArrayNotHasKey('sales', $report['totals']);
    }

    public function test_report_page_has_space_filter_payment_summary_and_collapsed_details(): void
    {
        [$company, $user] = $this->context();
        Space::factory()->create(['company_id' => $company->id, 'title' => 'Casa Norte']);
        $user->givePermissionTo('reports.view');
        $this->withoutVite();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('name="space_id"', false)
            ->assertSee('Resumen por tipo de pago')
            ->assertSee('<details class="card mt-3">', false)
            ->assertSee('Detalle de egresos')
            ->assertSee('Detalle de hospedaje')
            ->assertSee('Detalle de reservas')
            ->assertDontSee('Cajas')
            ->assertDontSee('Ventas POS');
    }

    public function test_pos_sales_report_type_is_no_longer_available(): void
    {
        $types = app(OperationalReportService::class)->reportTypes();

        $this->assertArrayNotHasKey('sales', $types);
        $this->assertArrayNotHasKey('cash', $types);
    }

    /**
     * @return array{0: Company, 1: User, 2: PaymentMethod, 3: SpaceCashRegister}
     */
    private function context(): array
    {
        Permission::findOrCreate('reports.view');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $method = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'Efectivo',
            'is_active' => true,
        ]);
        $register = SpaceCashRegister::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        return [$company, $user, $method, $register];
    }

    private function createLodgingPayment(
        Company $company,
        User $user,
        PaymentMethod $method,
        SpaceCashRegister $register,
        Space $space,
        string $receipt,
        float $amount,
    ): void {
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
        ]);
        $statement = AccountStatement::query()->create([
            'company_id' => $company->id,
            'stay_id' => $stay->id,
            'currency' => 'BOB',
            'subtotal' => $amount,
            'discount_total' => 0,
            'extra_charges_total' => 0,
            'payments_total' => $amount,
            'balance' => 0,
            'status' => 'paid',
        ]);

        SpaceCashLodgingPayment::query()->create([
            'company_id' => $company->id,
            'space_cash_register_id' => $register->id,
            'user_id' => $user->id,
            'account_statement_id' => $statement->id,
            'stay_id' => $stay->id,
            'check_in_group_id' => $stay->check_in_group_id,
            'payment_method_id' => $method->id,
            'receipt_number' => $receipt,
            'amount_original' => $amount,
            'currency_original' => 'BOB',
            'exchange_rate' => 1,
            'amount_bob' => $amount,
            'status' => 'active',
        ]);
    }
}

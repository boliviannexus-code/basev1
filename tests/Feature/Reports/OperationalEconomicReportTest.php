<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\Company;
use App\Models\ExtraChargeCategory;
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

    public function test_daily_report_defaults_to_today_and_filters_independently_from_period(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-20 12:00:00'));
        [$company, $user, $method, $register] = $this->context();
        $user->givePermissionTo('reports.view');
        $space = Space::factory()->create(['company_id' => $company->id]);
        $this->createLodgingPayment($company, $user, $method, $register, $space, 'HOS-TODAY', 80);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-19 12:00:00'));
        $this->createLodgingPayment($company, $user, $method, $register, $space, 'HOS-YESTERDAY', 120);
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-20 12:00:00'));
        $this->withoutVite();

        $this->actingAs($user)->get(route('reports.index'))->assertOk()->assertViewHas('isDaily', true)
            ->assertViewHas('totals', fn (array $totals): bool => $totals['lodging'] === 80.0)
            ->assertSee('Reporte diario')->assertSee('Reporte por periodo')->assertSee('name="date"', false);
        $this->get(route('reports.index', ['daily' => 1, 'date' => '2026-11-19']))->assertOk()
            ->assertViewHas('totals', fn (array $totals): bool => $totals['lodging'] === 120.0);
        $this->get(route('reports.index', ['daily' => 0, 'from' => '2026-11-19', 'to' => '2026-11-20']))->assertOk()
            ->assertViewHas('isDaily', false)->assertViewHas('totals', fn (array $totals): bool => $totals['lodging'] === 200.0)
            ->assertSee('name="from"', false)->assertSee('name="to"', false);
        $this->get(route('reports.index', ['from' => '2026-11-19', 'to' => '2026-11-20']))->assertOk()->assertViewHas('isDaily', false);
        Permission::findOrCreate('reports.print');
        $user->givePermissionTo('reports.print');
        $this->get(route('reports.print', ['daily' => 1, 'date' => '2026-11-19']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_statistics_include_all_movements_and_respect_company_and_date(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-20 12:00:00'));
        [$company, $user, $method, $register] = $this->context();
        $user->givePermissionTo('reports.view');
        $space = Space::factory()->create(['company_id' => $company->id]);
        $this->createLodgingPayment($company, $user, $method, $register, $space, 'HOS-ONE', 80);
        $rows = [];
        for ($i = 1; $i <= 501; $i++) {
            $rows[] = ['company_id' => $company->id, 'space_cash_register_id' => $register->id, 'user_id' => $user->id,
                'payment_method_id' => $method->id, 'receipt_number' => 'TEST-'.$i, 'detail' => 'Ingreso', 'amount' => 1,
                'received_at' => '2026-11-20 10:00:00', 'created_at' => now(), 'updated_at' => now()];
        }
        \Illuminate\Support\Facades\DB::table('space_cash_incomes')->insert($rows);
        [$otherCompany, $otherUser, $otherMethod, $otherRegister] = $this->context();
        $otherSpace = Space::factory()->create(['company_id' => $otherCompany->id]);
        $this->createLodgingPayment($otherCompany, $otherUser, $otherMethod, $otherRegister, $otherSpace, 'HOS-OTHER', 1000);
        $this->withoutVite();
        $response = $this->actingAs($user)->get(route('reports.index', ['tab' => 'statistics', 'from' => '2026-11-19', 'to' => '2026-11-20']));
        $response->assertOk()->assertViewHas('isStatistics', true)->assertSee('Ingresos y egresos por día')->assertSee('economic-bar-value', false)
            ->assertSee('581.00')->assertSee('Ingresos por método de pago')->assertSee('economic-pie', false)->assertSee('100.0%')
            ->assertViewHas('statistics', fn (array $stats): bool => $stats['income'] === 581.0 && $stats['daily']->count() === 2
                && $stats['daily'][0]['income'] === 0.0 && $stats['daily'][1]['income'] === 581.0);
        $this->get(route('reports.index', ['tab' => 'statistics', 'from' => '2026-11-19', 'to' => '2026-11-20', 'space_id' => $space->id]))
            ->assertOk()->assertViewHas('statistics', fn (array $stats): bool => $stats['income'] === 80.0);
        $this->get(route('reports.index', ['tab' => 'statistics', 'from' => '2026-11-18', 'to' => '2026-11-19']))
            ->assertOk()->assertSee('No hay ingresos para mostrar en la torta.');
        $this->get(route('reports.index', ['tab' => 'statistics', 'from' => '2024-01-01', 'to' => '2026-11-20']))
            ->assertOk()->assertSee('hasta 366 días')->assertViewHas('statistics', null);
    }

    public function test_pos_sales_report_type_is_no_longer_available(): void
    {
        $types = app(OperationalReportService::class)->reportTypes();

        $this->assertArrayNotHasKey('sales', $types);
        $this->assertArrayNotHasKey('cash', $types);
    }

    public function test_stay_extras_are_filtered_separately_without_increasing_cash_income(): void
    {
        [$company, $user, $method, $register] = $this->context();
        $space = Space::factory()->create(['company_id' => $company->id]);
        $this->createLodgingPayment($company, $user, $method, $register, $space, 'HOS-EXTRA', 80);
        $statement = AccountStatement::query()->where('company_id', $company->id)->firstOrFail();
        $category = ExtraChargeCategory::query()->create(['company_id' => $company->id, 'name' => 'Lavanderia', 'is_active' => true]);
        $attributes = [
            'company_id' => $company->id, 'account_statement_id' => $statement->id,
            'stay_id' => $statement->stay_id, 'extra_charge_category_id' => $category->id,
            'date' => now()->subDay()->toDateString(), 'type' => 'extra',
            'description' => 'Lavado de ropa', 'quantity' => 2, 'unit_price' => 15,
            'total' => 30, 'currency' => 'BOB', 'source' => 'manual',
            'status' => 'active', 'created_by' => $user->id,
        ];
        $charge = AccountStatementItem::query()->create($attributes);
        AccountStatementItem::query()->create(array_replace($attributes, ['status' => 'cancelled']));
        AccountStatementItem::query()->create(array_replace($attributes, ['date' => now()->subMonth()->toDateString()]));
        AccountStatementItem::query()->create(array_replace($attributes, ['extra_charge_category_id' => null]));
        $otherCompany = Company::factory()->create();
        AccountStatementItem::query()->create(array_replace($attributes, ['company_id' => $otherCompany->id]));

        $service = app(OperationalReportService::class);
        $filters = $service->filters([
            'from' => now()->subDay()->toDateString(), 'to' => now()->toDateString(),
            'category_id' => $category->id, 'space_id' => $space->id, 'user_id' => $user->id,
            'payment_method_id' => $method->id,
        ]);
        $report = $service->build($company->id, $filters);
        $this->assertSame([$charge->id], $report['stayExtraCharges']->modelKeys());
        $this->assertSame(30.0, $report['stayExtraChargeTotals']['BOB']);
        $this->assertCount(0, $report['incomeRows']);
        $unfiltered = $service->build($company->id, array_replace($filters, ['category_id' => null]));
        $this->assertSame(80.0, $unfiltered['totals']['lodging']);
        $this->assertSame(80.0, (float) $unfiltered['incomeRows']->sum('amount'));
        $this->assertCount(0, $service->build($company->id, array_replace($filters, ['user_id' => $user->id + 1000]))['stayExtraCharges']);
        $this->assertCount(0, $service->build($company->id, array_replace($filters, ['space_id' => $space->id + 1000]))['stayExtraCharges']);

        $user->givePermissionTo('reports.view');
        $this->withoutVite();
        $this->actingAs($user)->get(route('reports.index', [
            'from' => $filters['from']->toDateString(), 'to' => $filters['to']->toDateString(),
            'category_id' => $category->id,
        ]))->assertOk()->assertSee('Detalle de cargos extra de estancias')->assertSee('Lavado de ropa');
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

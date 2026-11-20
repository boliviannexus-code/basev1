<?php

namespace Tests\Feature\Reports;

use App\Models\AccountStatement;
use App\Models\Reservation;
use App\Models\ReservationBedUnit;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Models\User;
use App\Services\Reports\OperationalReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PendingCheckoutReportTest extends TestCase
{
    use RefreshDatabase;

    public static function resources(): array
    {
        return ['cama' => ['bed'], 'habitación' => ['room'], 'espacio' => ['space']];
    }

    #[DataProvider('resources')]
    public function test_pending_checkout_and_arrival_are_separate_for_each_resource(string $type): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-18 10:00'));
        $stay = Stay::factory()->create(['check_in_date' => '2026-11-15', 'check_out_date' => $type === 'space' ? '2026-11-17' : '2026-11-18', 'status' => 'occupied']);
        $space = Space::findOrFail($stay->space_id);
        $space->update(['status' => 'active']);
        $room = $type === 'space' ? null : SpaceRoom::factory()->create(['company_id' => $stay->company_id, 'space_id' => $space->id, 'status' => 'active', 'sale_mode' => $type === 'bed' ? 'bed_unit' : 'full_room']);
        $bed = $type === 'bed' ? RoomBedUnit::factory()->create(['company_id' => $stay->company_id, 'space_room_id' => $room->id, 'status' => 'active']) : null;
        $stay->update(['space_room_id' => $room?->id, 'room_bed_unit_id' => $bed?->id]);
        AccountStatement::create(['company_id' => $stay->company_id, 'stay_id' => $stay->id, 'currency' => 'BOB', 'subtotal' => 200, 'discount_total' => 0, 'extra_charges_total' => 0, 'payments_total' => 50, 'balance' => 150, 'status' => 'partial']);
        $reservation = Reservation::factory()->create(['company_id' => $stay->company_id, 'space_id' => $space->id, 'space_room_id' => $room?->id, 'guest_name' => 'Reserva entrante', 'check_in' => '2026-11-18', 'check_out' => '2026-11-19', 'status' => 'confirmed']);
        if ($bed) {
            ReservationBedUnit::create(['reservation_id' => $reservation->id, 'room_bed_unit_id' => $bed->id, 'guest_name' => 'Reserva entrante', 'price_per_night' => 100, 'subtotal_amount' => 100]);
        }
        $service = app(OperationalReportService::class);
        $filters = $service->filters(['space_id' => $space->id]);
        $rows = $service->dailyOccupancy($stay->company_id, CarbonImmutable::today(), $filters)['rooms'];
        $this->assertSame(['pending_checkout', 'reserved'], $rows->pluck('status')->all());
        $this->assertEquals(150, $rows[0]['balance']);
        $this->assertEquals(50, $rows[0]['advance']);
        $this->assertEquals(200, $rows[0]['total']);
        $this->assertSame('Reserva entrante', $rows[1]['guest']);
        $this->assertFalse($service->dailyOccupancy($stay->company_id, CarbonImmutable::tomorrow(), $filters)['rooms']->contains('status', 'pending_checkout'));
        $reservation->update(['status' => 'cancelled']);
        $rows = $service->dailyOccupancy($stay->company_id, CarbonImmutable::today(), $filters)['rooms'];
        $this->assertSame(['pending_checkout'], $rows->pluck('status')->all());
        $stay->update(['status' => 'checked_out', 'checked_out_at' => now()]);
        $this->assertFalse($service->dailyOccupancy($stay->company_id, CarbonImmutable::today(), $filters)['rooms']->contains('status', 'pending_checkout'));
    }

    public function test_daily_page_and_pdf_show_pending_checkout(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-18 10:00'));
        $stay = Stay::factory()->create(['check_in_date' => '2026-11-17', 'check_out_date' => '2026-11-18']);
        Space::findOrFail($stay->space_id)->update(['status' => 'active']);
        Permission::findOrCreate('reports.view');
        Permission::findOrCreate('reports.print');
        $user = User::factory()->create(['company_id' => $stay->company_id]);
        $user->givePermissionTo(['reports.view', 'reports.print']);
        $this->withoutVite();
        $this->actingAs($user)->get(route('reports.occupancy.index'))->assertOk()->assertSee('Check-out pendiente');
        $this->get(route('reports.occupancy.print'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $service = app(OperationalReportService::class);
        $daily = $service->dailyOccupancy($stay->company_id, CarbonImmutable::today(), $service->filters([]));
        $pdfHtml = view('reports.occupancy.daily-pdf', [
            'dailyOccupancy' => $daily, 'company' => $stay->company, 'logoPath' => null,
            'printedBy' => $user, 'reportTitle' => 'Reporte diario',
        ])->render();
        $this->assertStringContainsString('CP = Check-out pendiente', $pdfHtml);
        $this->assertStringContainsString('>CP</td>', $pdfHtml);
        $this->assertStringContainsString($stay->holderGuest->full_name, $pdfHtml);
        $this->assertStringContainsString('Importes en BOB', $pdfHtml);
        $this->assertStringContainsString('width="5%"', $pdfHtml);
        $this->assertStringContainsString('width="32%"', $pdfHtml);
    }
}

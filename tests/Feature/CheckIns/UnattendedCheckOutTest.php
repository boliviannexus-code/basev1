<?php

namespace Tests\Feature\CheckIns;

use App\Models\AvailabilityStatus;
use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Space;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use App\Services\CheckIn\CheckInUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UnattendedCheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_command_never_extends_stays(): void
    {
        [$user, $stay] = $this->context();
        $stay->update(['check_out_date' => today()]);
        $before = $stay->fresh()->getAttributes();
        $this->artisan('checkouts:extend-unresolved')->assertSuccessful();
        $this->assertSame($before, $stay->fresh()->getAttributes());
        $this->assertDatabaseCount('account_statement_items', 1);
    }

    public function test_module_requires_separate_permission_and_scopes_cases(): void
    {
        [$user, $stay] = $this->context();
        $this->actingAs($user)->get(route('unattended-checkouts.index'))->assertForbidden();
        $this->postJson(route('unattended-checkouts.resolve', $stay), $this->payload($stay))->assertForbidden();
        $user->givePermissionTo('unattended-checkouts.manage');
        $other = Stay::factory()->create(['check_in_date' => today()->subDays(2), 'check_out_date' => today()->subDay()]);
        $current = Stay::factory()->create(['company_id' => $user->company_id, 'check_in_date' => today()->subDay(), 'check_out_date' => today()]);
        $this->get(route('unattended-checkouts.index'))->assertOk()
            ->assertSee(route('unattended-checkouts.resolve', $stay), false)
            ->assertDontSee(route('unattended-checkouts.resolve', $other), false)
            ->assertDontSee(route('unattended-checkouts.resolve', $current), false);
        $this->postJson(route('unattended-checkouts.resolve', $other), $this->payload($other))->assertNotFound();
        $this->postJson(route('unattended-checkouts.resolve', $current), $this->payload($current))->assertUnprocessable();
    }

    public function test_module_lists_past_arrival_reservations_and_resolves_them_manually(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        $pastGroup = ReservationGroup::factory()->create([
            'company_id' => $user->company_id,
            'code' => 'RSG-PAST-0001',
            'guest_name' => 'Reserva Grupo Atrasada',
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);
        Reservation::factory()->create([
            'company_id' => $user->company_id,
            'reservation_group_id' => $pastGroup->id,
            'guest_name' => $pastGroup->guest_name,
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);
        $pastSingle = Reservation::factory()->create([
            'company_id' => $user->company_id,
            'reservation_group_id' => null,
            'code' => 'RSV-PAST-0001',
            'guest_name' => 'Reserva Individual Atrasada',
            'check_in' => today()->subDays(2),
            'check_out' => today()->subDay(),
            'status' => 'payment_under_review',
        ]);
        $todayReservation = Reservation::factory()->create([
            'company_id' => $user->company_id,
            'reservation_group_id' => null,
            'code' => 'RSV-TODAY-0001',
            'check_in' => today(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);

        $this->actingAs($user)->get(route('unattended-checkouts.index'))
            ->assertOk()
            ->assertSee('Reserva Grupo Atrasada')
            ->assertSee('Reserva Individual Atrasada')
            ->assertSee(route('unattended-checkouts.reservation-groups.resolve', $pastGroup), false)
            ->assertSee(route('unattended-checkouts.reservations.resolve', $pastSingle), false)
            ->assertDontSee('RSV-TODAY-0001');

        $this->post(route('unattended-checkouts.reservation-groups.resolve', $pastGroup), [
            'action' => 'no_show',
            'expected_check_in' => $pastGroup->check_in->toDateString(),
            'reason' => 'El huesped no llego y no respondio.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->post(route('unattended-checkouts.reservations.resolve', $pastSingle), [
            'action' => 'cancel',
            'expected_check_in' => $pastSingle->check_in->toDateString(),
            'reason' => 'Cancelacion acordada con recepcion.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('no_show', $pastGroup->refresh()->status);
        $this->assertSame('cancelled', $pastSingle->refresh()->status);
        $this->assertStringContainsString('El huesped no llego y no respondio.', $pastGroup->notes);
        $this->assertStringContainsString('Cancelacion acordada con recepcion.', $pastSingle->guest_notes);
        $this->assertSame('confirmed', $todayReservation->refresh()->status);
        $this->assertSame('occupied', $stay->refresh()->status);
    }

    public function test_past_arrival_reservation_resolution_prevents_stale_replay(): void
    {
        [$user] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        $reservation = Reservation::factory()->create([
            'company_id' => $user->company_id,
            'reservation_group_id' => null,
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);
        $payload = [
            'action' => 'cancel',
            'expected_check_in' => $reservation->check_in->toDateString(),
            'reason' => 'Comentario de prueba',
        ];
        $reservation->update(['check_in' => today()->toDateString()]);

        $this->actingAs($user)
            ->postJson(route('unattended-checkouts.reservations.resolve', $reservation), $payload)
            ->assertUnprocessable();

        $this->assertSame('confirmed', $reservation->refresh()->status);
    }

    public function test_extension_adds_exactly_one_night_and_preserves_guests_and_prevents_replay(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        $guest = Guest::factory()->create(['company_id' => $user->company_id]);
        $stay->guests()->attach($guest, ['company_id' => $user->company_id, 'is_holder' => false]);
        $payload = $this->payload($stay);
        $this->actingAs($user)->post(route('unattended-checkouts.resolve', $stay), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(today()->toDateString(), $stay->refresh()->check_out_date->toDateString());
        $this->assertSame(2, $stay->nights);
        $this->assertTrue($stay->guests()->whereKey($guest->id)->exists());
        $this->assertSame(today()->toDateString(), $stay->checkInGroup->check_out_date->toDateString());
        $this->assertDatabaseCount('account_statement_items', 2);
        $this->assertEquals(200, $stay->accountStatement->balance);
        $this->postJson(route('unattended-checkouts.resolve', $stay), $payload)->assertUnprocessable();
        $this->assertDatabaseCount('account_statement_items', 2);
    }

    public function test_older_case_remains_in_module_after_one_night(): void
    {
        [$user, $stay] = $this->context();
        $stay->checkInGroup->update(['check_in_date' => today()->subDays(4)]);
        $stay->update(['check_in_date' => today()->subDays(4), 'check_out_date' => today()->subDays(3)]);
        $user->givePermissionTo('unattended-checkouts.manage');
        $this->actingAs($user)->post(route('unattended-checkouts.resolve', $stay), $this->payload($stay))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($stay->refresh()->isUnattended());
        $this->assertSame(today()->subDays(2)->toDateString(), $stay->check_out_date->toDateString());
    }

    public function test_extension_respects_availability_conflicts(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        AvailabilityStatus::query()->create([
            'company_id' => $user->company_id, 'space_id' => $stay->space_id,
            'date' => $stay->check_out_date, 'status' => 'reserved',
        ]);
        $this->actingAs($user)->postJson(route('unattended-checkouts.resolve', $stay), $this->payload($stay))->assertUnprocessable();
        $this->assertSame(1, $stay->refresh()->nights);
        $this->assertDatabaseCount('account_statement_items', 1);
    }

    public function test_late_checkout_requires_payment_and_keeps_original_date_and_charges(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        $payload = $this->payload($stay, 'checkout') + ['reason' => 'Salida registrada al dia siguiente por recepcion.'];
        $this->actingAs($user)->postJson(route('unattended-checkouts.resolve', $stay), $payload)->assertUnprocessable();
        app(AccountStatementService::class)->recordPayment($stay, 100, 'Pago total');
        $this->post(route('unattended-checkouts.resolve', $stay), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('checked_out', $stay->refresh()->status);
        $this->assertSame($payload['expected_check_out_date'], $stay->check_out_date->toDateString());
        $this->assertSame(1, $stay->nights);
        $this->assertStringContainsString('Salida registrada al dia siguiente por recepcion.', $stay->notes);
        $this->assertSame('checked_out', $stay->checkInGroup->status);
    }

    public function test_manual_resolution_requires_comment_for_history(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        app(AccountStatementService::class)->recordPayment($stay, 100, 'Pago total');

        $this->actingAs($user)
            ->postJson(route('unattended-checkouts.resolve', $stay), $this->payload($stay, 'checkout'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $reservation = Reservation::factory()->create([
            'company_id' => $user->company_id,
            'reservation_group_id' => null,
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);

        $this->postJson(route('unattended-checkouts.reservations.resolve', $reservation), [
            'action' => 'no_show',
            'expected_check_in' => $reservation->check_in->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_normal_checkout_cannot_bypass_module_even_with_special_permission(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        app(AccountStatementService::class)->recordPayment($stay, 100, 'Pago total');
        $this->actingAs($user)->postJson(route('occupancy.check-out.store', $stay))->assertForbidden();
        $this->assertSame('occupied', $stay->refresh()->status);
    }

    public function test_normal_stay_update_cannot_bypass_module(): void
    {
        [$user, $stay] = $this->context();
        $user->givePermissionTo('unattended-checkouts.manage');
        $this->expectException(HttpException::class);
        app(CheckInUpdateService::class)->updateStay($stay, ['check_out_date' => today()->toDateString()], $user);
    }

    private function payload(Stay $stay, string $action = 'extend'): array
    {
        return ['action' => $action, 'expected_check_out_date' => $stay->check_out_date->toDateString()];
    }

    private function context(): array
    {
        $this->travelTo(today()->setTime(12, 0));
        Permission::findOrCreate('occupancy.manage');
        Permission::findOrCreate('unattended-checkouts.manage');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage');
        $space = Space::factory()->create(['company_id' => $company->id, 'max_capacity' => 4]);
        $group = CheckInGroup::factory()->create(['company_id' => $company->id, 'check_in_date' => today()->subDays(2), 'check_out_date' => today()->subDay()]);
        $stay = Stay::factory()->create([
            'check_in_group_id' => $group->id,
            'company_id' => $company->id, 'space_id' => $space->id,
            'check_in_date' => today()->subDays(2), 'check_out_date' => today()->subDay(),
            'nights' => 1, 'people_count' => 1, 'price_per_night_bob' => 100, 'currency' => 'BOB', 'status' => 'occupied',
        ]);
        app(AccountStatementService::class)->createForStay($stay);

        return [$user, $stay];
    }
}

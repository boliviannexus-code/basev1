<?php

namespace Tests\Feature\CheckIns;

use App\Models\CheckOutAlertEscalation;
use App\Models\CheckOutAlertSnooze;
use App\Models\Company;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CheckOutAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_checkout_alert_can_be_snoozed_for_company_interval(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        Permission::findOrCreate('occupancy.manage');
        $company = Company::factory()->create([
            'check_out_time' => '11:00',
            'check_out_alert_snooze_minutes' => 20,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage');
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'status' => 'occupied',
            'check_in_date' => today()->subDay(),
            'check_out_date' => today(),
        ]);
        Stay::factory()->create([
            'company_id' => $company->id,
            'status' => 'occupied',
            'check_in_date' => today()->subMonth()->subDay(),
            'check_out_date' => today()->subMonth(),
        ]);

        $this->actingAs($user)
            ->getJson(route('checkout-alerts.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $stay->id)
            ->assertJsonPath('data.0.snooze_minutes', 20)
            ->assertJsonPath('data.0.check_in_url', route('check-ins.edit', $stay->check_in_group_id));

        $this->postJson(route('checkout-alerts.snooze', $stay))->assertOk();

        $this->assertDatabaseHas(CheckOutAlertSnooze::class, [
            'user_id' => $user->id,
            'stay_id' => $stay->id,
        ]);
        $this->getJson(route('checkout-alerts.index'))->assertJsonCount(0, 'data');

        $this->travel(21)->minutes();
        $this->getJson(route('checkout-alerts.index'))->assertJsonPath('data.0.id', $stay->id);
    }

    public function test_alerts_do_not_appear_before_configured_checkout_time(): void
    {
        $this->travelTo(today()->setTime(10, 0));
        Permission::findOrCreate('occupancy.manage');
        $company = Company::factory()->create(['check_out_time' => '11:00']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage');
        Stay::factory()->create([
            'company_id' => $company->id,
            'status' => 'occupied',
            'check_in_date' => today()->subDay(),
            'check_out_date' => today(),
        ]);

        $this->actingAs($user)
            ->getJson(route('checkout-alerts.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_escalated_conflict_from_an_earlier_date_is_ignored(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        Permission::findOrCreate('occupancy.manage');
        $company = Company::factory()->create(['check_out_time' => '11:00']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage');
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'status' => 'occupied',
            'check_in_date' => today()->subDays(3),
            'check_out_date' => today()->subDays(2),
        ]);
        CheckOutAlertEscalation::query()->create([
            'company_id' => $company->id,
            'stay_id' => $stay->id,
            'reason' => 'La habitacion ya tiene una reserva.',
        ]);

        $this->actingAs($user)
            ->getJson(route('checkout-alerts.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}

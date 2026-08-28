<?php

namespace Tests\Feature\ReservationSettings;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_update_reservation_advance_percentage(): void
    {
        Permission::findOrCreate('reservation-settings.manage');
        $company = Company::factory()->create([
            'reservation_advance_percentage' => null,
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('reservation-settings.manage');

        $this->actingAs($user)
            ->get(route('reservation-settings.edit'))
            ->assertOk()
            ->assertSee('Configuracion de reservas')
            ->assertSee('Porcentaje de adelanto');

        $this->actingAs($user)
            ->put(route('reservation-settings.update'), [
                'reservation_advance_percentage' => 35.5,
                'check_out_time' => '10:30',
                'check_out_alert_snooze_minutes' => 20,
            ])
            ->assertRedirect(route('reservation-settings.edit'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'reservation_advance_percentage' => '35.50',
            'check_out_time' => '10:30:00',
            'check_out_alert_snooze_minutes' => 20,
        ]);
    }
}

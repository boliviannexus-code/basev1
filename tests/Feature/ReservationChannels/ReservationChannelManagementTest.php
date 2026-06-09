<?php

namespace Tests\Feature\ReservationChannels;

use App\Models\Company;
use App\Models\ReservationChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationChannelManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_manage_own_reservation_channels(): void
    {
        $user = $this->companyUser();

        $this
            ->actingAs($user)
            ->post(route('reservation-channels.store'), [
                'name' => 'Agencia X',
                'type' => 'agency',
                'commission_percent' => 12.5,
                'is_active' => '1',
            ])
            ->assertRedirect(route('reservation-channels.index'));

        $channel = ReservationChannel::query()->where('company_id', $user->company_id)->firstOrFail();

        $this->assertSame('Agencia X', $channel->name);
        $this->assertSame('agencia-x', $channel->slug);
        $this->assertSame('agency', $channel->type);

        $this
            ->actingAs($user)
            ->put(route('reservation-channels.update', $channel), [
                'name' => 'Agencia Y',
                'slug' => 'agencia-y',
                'type' => 'agency',
                'commission_percent' => 10,
                'is_active' => '1',
            ])
            ->assertRedirect(route('reservation-channels.index'));

        $this->assertSame('Agencia Y', $channel->refresh()->name);
        $this->assertSame('agencia-y', $channel->slug);
    }

    public function test_company_user_cannot_manage_other_company_channel(): void
    {
        $user = $this->companyUser();
        $otherChannel = ReservationChannel::factory()->create([
            'company_id' => Company::factory()->create()->id,
            'name' => 'Booking externo',
            'slug' => 'booking-externo',
        ]);

        $this
            ->actingAs($user)
            ->put(route('reservation-channels.update', $otherChannel), [
                'name' => 'Editado',
                'slug' => 'editado',
                'type' => 'ota',
                'is_active' => '1',
            ])
            ->assertNotFound();
    }

    private function companyUser(): User
    {
        Permission::findOrCreate('reservation-channels.manage');

        $user = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);
        $user->givePermissionTo('reservation-channels.manage');

        return $user;
    }
}

<?php

namespace Tests\Feature\Reservations;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\PrivateSpaceType;
use App\Models\OccupancyBlock;
use App\Models\ReservationGroup;
use App\Models\ReservationChannel;
use App\Models\SpaceCashRegister;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InternalReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_grouped_internal_reservation_for_multiple_spaces(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $firstSpace, $secondSpace, $country, $channel] = $this->context();

        $response = $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), [
                'check_in_type' => 'multiple',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'phone' => '76543210',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 4,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(3)->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $firstSpace->id,
                        'people_count' => 2,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'guests' => [],
                    ],
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $secondSpace->id,
                        'people_count' => 2,
                        'price_per_night_bob' => 120,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'guests' => [],
                    ],
                ],
            ]);

        $response->assertRedirect();

        $group = ReservationGroup::query()->firstOrFail();

        $this->assertSame('pending_payment', $group->status);
        $this->assertSame('pending', $group->payment_status);
        $this->assertSame('76543210', $group->guest_phone);
        $this->assertSame(2, $group->reservations()->count());
        $this->assertSame('400.00', $group->total_amount);
        $this->assertSame('0.00', $group->advance_amount);
        $this->assertSame('400.00', $group->balance_amount);
        $this->assertNotNull($group->accountStatement);
        $this->assertSame('400.00', $group->accountStatement->balance);
        $this->assertSame(4, $group->accountStatement->items()->count());
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $user->company_id,
            'space_id' => $firstSpace->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $user->company_id,
            'space_id' => $secondSpace->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);
    }

    public function test_staff_can_register_reservation_advance_after_creation_through_space_cash(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space,, $country, $channel, $paymentMethod] = $this->context();

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), [
                'check_in_type' => 'individual',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 1,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $space->id,
                        'people_count' => 1,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'breakfast_included' => true,
                        'guests' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $group = ReservationGroup::query()->firstOrFail();
        SpaceCashRegister::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);
        $cashRegister = SpaceCashRegister::query()->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('admin.reservation-groups.payments.store', $group), [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 50,
                'reference' => 'QR-001',
            ])
            ->assertRedirect(route('admin.reservation-groups.show', $group));

        $group->refresh()->load('accountStatement');
        $this->assertSame('partial', $group->payment_status);
        $this->assertSame('50.00', $group->advance_amount);
        $this->assertSame('30.00', $group->balance_amount);
        $this->assertSame('30.00', $group->accountStatement->balance);
        $this->assertDatabaseHas('space_cash_reservation_payments', [
            'company_id' => $user->company_id,
            'space_cash_register_id' => $cashRegister->id,
            'reservation_group_id' => $group->id,
            'amount_bob' => 50,
            'status' => 'active',
        ]);
    }

    public function test_reservation_advance_requires_open_space_cash_register(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space,, $country, $channel, $paymentMethod] = $this->context();

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), [
                'check_in_type' => 'individual',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 1,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $space->id,
                        'people_count' => 1,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'guests' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $group = ReservationGroup::query()->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('admin.reservation-groups.payments.store', $group), [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 50,
                'reference' => 'QR-001',
            ])
            ->assertSessionHasErrors(['amount'], null, 'reservationPayment');

        $this->assertDatabaseMissing('space_cash_reservation_payments', [
            'company_id' => $user->company_id,
            'reservation_group_id' => $group->id,
        ]);
    }

    public function test_staff_can_send_today_reservation_to_prefilled_check_in_form(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space,, $country, $channel] = $this->context();

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), [
                'check_in_type' => 'individual',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 2,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $space->id,
                        'people_count' => 2,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'guests' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $group = ReservationGroup::query()->with('reservations.occupancyBlock')->firstOrFail();

        $this
            ->actingAs($user)
            ->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSee('Check-in')
            ->assertDontSee('Disponible solo en la fecha de ingreso');

        $this
            ->actingAs($user)
            ->post(route('admin.reservation-groups.check-in', $group))
            ->assertRedirect(route('check-ins.create', ['reservation_group_id' => $group->id]))
            ->assertSessionHas('success');

        $group->refresh()->load('reservations.occupancyBlock');

        $this->assertSame('checked_in', $group->status);
        $this->assertSame('checked_in', $group->reservations->first()->status);
        $this->assertSame('active', $group->reservations->first()->occupancyBlock()->withTrashed()->first()?->status);

        ExtraChargeCategory::ensureDefaultsForCompany((int) $user->company_id);
        $category = ExtraChargeCategory::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->firstOrFail();
        $reservation = $group->reservations->first();

        $this
            ->actingAs($user)
            ->get(route('admin.reservations.extra-charges.create', $reservation))
            ->assertOk();

        $this
            ->actingAs($user)
            ->post(route('admin.reservations.extra-charges.store', $reservation), [
                'extra_charge_category_id' => $category->id,
                'date' => now()->toDateString(),
                'detail' => 'Toalla adicional',
                'quantity' => 1,
                'unit_price' => 15,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reservation_extra_charges', [
            'company_id' => $user->company_id,
            'reservation_id' => $reservation->id,
            'extra_charge_category_id' => $category->id,
            'detail' => 'Toalla adicional',
            'total' => 15,
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSee('Continuar check-in');

        $this
            ->actingAs($user)
            ->post(route('admin.reservation-groups.check-in', $group))
            ->assertRedirect(route('check-ins.create', ['reservation_group_id' => $group->id]));

        $this
            ->actingAs($user)
            ->get(route('check-ins.create', ['reservation_group_id' => $group->id]))
            ->assertOk()
            ->assertSee('value="123456"', false)
            ->assertSee('value="Ana"', false)
            ->assertSee('value="Perez"', false)
            ->assertSee('value="2"', false)
            ->assertSee('value="'.$space->id.'"', false)
            ->assertSee('value="80.00"', false)
            ->assertSee('value="11.49"', false)
            ->assertSee('checked', false);

        $this
            ->actingAs($user)
            ->post(route('check-ins.store'), [
                'reservation_group_id' => $group->id,
                'confirm_reserved_conversion' => 1,
                'check_in_type' => 'individual',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 2,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $space->id,
                        'people_count' => 2,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'breakfast_included' => true,
                        'guests' => [],
                    ],
                ],
            ])
            ->assertRedirect(route('occupancy.index'));

        $checkIn = CheckInGroup::query()->with('stays')->firstOrFail();

        $this->assertSame(1, $checkIn->stays->count());
        $this->assertTrue((bool) $checkIn->stays->first()->breakfast_included);
        $this->assertSame('cancelled', OccupancyBlock::withTrashed()->find($group->reservations->first()->occupancy_block_id)?->status);
        $this->assertSoftDeleted('occupancy_blocks', [
            'id' => $group->reservations->first()->occupancy_block_id,
        ]);
    }

    public function test_reservation_check_in_button_is_only_enabled_on_check_in_date(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space,, $country, $channel] = $this->context();

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), [
                'check_in_type' => 'individual',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 1,
                'check_in_date' => now()->addDay()->toDateString(),
                'check_out_date' => now()->addDays(2)->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $space->id,
                        'people_count' => 1,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'guests' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $group = ReservationGroup::query()->firstOrFail();

        $this
            ->actingAs($user)
            ->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSee('Disponible solo en la fecha de ingreso')
            ->assertSee('disabled', false);

        $this
            ->actingAs($user)
            ->post(route('admin.reservation-groups.check-in', $group))
            ->assertRedirect(route('admin.reservation-groups.show', $group))
            ->assertSessionHasErrors('check_in');

        $this->assertSame('pending_payment', $group->refresh()->status);
    }

    public function test_staff_can_cancel_reservation_after_it_was_sent_to_check_in(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space,, $country, $channel] = $this->context();

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), [
                'check_in_type' => 'individual',
                'document_type' => 'ci',
                'document_number' => '123456',
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'birth_country_id' => $country->id,
                'birth_date' => now()->subYears(30)->toDateString(),
                'reservation_channel_id' => $channel->id,
                'total_people' => 1,
                'check_in_date' => now()->toDateString(),
                'check_out_date' => now()->addDay()->toDateString(),
                'stays' => [
                    [
                        'resource_type' => 'private_space',
                        'space_id' => $space->id,
                        'people_count' => 1,
                        'price_per_night_bob' => 80,
                        'currency' => 'BOB',
                        'exchange_rate' => 6.96,
                        'guests' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $group = ReservationGroup::query()->with('reservations.occupancyBlock')->firstOrFail();
        $blockId = $group->reservations->first()->occupancy_block_id;

        $this
            ->actingAs($user)
            ->post(route('admin.reservation-groups.check-in', $group))
            ->assertRedirect(route('check-ins.create', ['reservation_group_id' => $group->id]));

        $group->refresh()->load('reservations.occupancyBlock');

        $this->assertSame('checked_in', $group->status);
        $this->assertSame('active', OccupancyBlock::withTrashed()->find($blockId)?->status);

        $this
            ->actingAs($user)
            ->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSee('Continuar check-in')
            ->assertSee('Cancelar reserva');

        $this
            ->actingAs($user)
            ->patch(route('admin.reservation-groups.cancel', $group), [
                'reason' => 'No completo el check-in',
            ])
            ->assertRedirect(route('admin.reservation-groups.show', $group));

        $this->assertSame('cancelled', $group->refresh()->status);
        $this->assertSame('cancelled', $group->reservations()->firstOrFail()->status);
        $this->assertSame('cancelled', OccupancyBlock::withTrashed()->find($blockId)?->status);
        $this->assertSoftDeleted('occupancy_blocks', ['id' => $blockId]);
    }

    public function test_staff_can_cancel_grouped_reservation_and_reuse_released_space(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space,, $country, $channel] = $this->context();

        $payload = [
            'check_in_type' => 'individual',
            'document_type' => 'ci',
            'document_number' => '123456',
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'birth_country_id' => $country->id,
            'birth_date' => now()->subYears(30)->toDateString(),
            'reservation_channel_id' => $channel->id,
            'total_people' => 1,
            'check_in_date' => now()->addDay()->toDateString(),
            'check_out_date' => now()->addDays(2)->toDateString(),
            'stays' => [
                [
                    'resource_type' => 'private_space',
                    'space_id' => $space->id,
                    'people_count' => 1,
                    'price_per_night_bob' => 80,
                    'currency' => 'BOB',
                    'exchange_rate' => 6.96,
                    'guests' => [],
                ],
            ],
        ];

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), $payload)
            ->assertRedirect();

        $group = ReservationGroup::query()->with('reservations.occupancyBlock')->firstOrFail();
        $reservation = $group->reservations->first();
        $blockId = $reservation->occupancy_block_id;

        $this
            ->actingAs($user)
            ->patch(route('admin.reservation-groups.cancel', $group), [
                'reason' => 'Cambio de planes',
            ])
            ->assertRedirect(route('admin.reservation-groups.show', $group));

        $this
            ->actingAs($user)
            ->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSee('Cancelada')
            ->assertDontSee('Cancelar reserva');

        $group->refresh();
        $reservation->refresh();

        $this->assertSame('cancelled', $group->status);
        $this->assertSame('cancelled', $reservation->status);
        $this->assertSame('cancelled', OccupancyBlock::withTrashed()->find($blockId)?->status);
        $this->assertSoftDeleted('occupancy_blocks', ['id' => $blockId]);

        $payload['document_number'] = '654321';
        $payload['first_name'] = 'Luis';

        $this
            ->actingAs($user)
            ->post(route('internal-reservations.store'), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, ReservationGroup::query()->count());
    }

    public function test_reservation_group_edit_shows_nightly_price_and_nights_in_their_columns(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $space] = $this->context();

        $group = ReservationGroup::factory()->create([
            'company_id' => $user->company_id,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana@example.test',
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(4)->toDateString(),
            'nights' => 2,
            'subtotal_amount' => 160,
            'total_amount' => 160,
            'balance_amount' => 160,
        ]);
        $reservation = $group->reservations()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'space_id' => $space->id,
            'code' => 'RSV-TEST-0001',
            'guest_name' => $group->guest_name,
            'guest_email' => $group->guest_email,
            'check_in' => $group->check_in,
            'check_out' => $group->check_out,
            'nights' => 2,
            'guests' => 2,
            'price_per_person' => 80,
            'subtotal_amount' => 160,
            'total_amount' => 160,
            'advance_amount' => 0,
            'balance_amount' => 160,
            'currency' => 'BOB',
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);

        $this
            ->actingAs($user)
            ->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSeeInOrder([
                '<th class="text-end">Precio BOB</th>',
                '<th class="text-end">Precio USD</th>',
                '<th class="text-end">Noches</th>',
                'name="reservations['.$reservation->id.'][price_per_night_bob]"',
                'value="80.00"',
                'name="reservations['.$reservation->id.'][price_per_night_usd]"',
                'name="reservations['.$reservation->id.'][nights]"',
                'value="2"',
            ], false);

        $this
            ->actingAs($user)
            ->patch(route('admin.reservation-groups.update', $group), [
                'reservation_channel_id' => null,
                'guest_name' => 'Ana Perez',
                'guest_email' => '',
                'guest_phone' => '',
                'guest_document' => '',
                'reservations' => [
                    $reservation->id => [
                        'check_in' => now()->addDays(2)->toDateString(),
                        'check_out' => now()->addDays(4)->toDateString(),
                        'nights' => 2,
                        'price_per_night_usd' => 10,
                        'exchange_rate' => 6.96,
                        'currency' => 'BOB',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.reservation-groups.show', $group))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull($group->refresh()->guest_email);
        $this->assertSame('69.60', $reservation->refresh()->price_per_person);
        $this->assertSame('139.20', $reservation->subtotal_amount);
        $this->assertSame('ana@example.test', $reservation->refresh()->guest_email);
    }

    private function context(): array
    {
        Permission::findOrCreate('occupancy.manage');
        Permission::findOrCreate('reservations.manage');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage', 'reservations.manage');
        $country = Country::factory()->create([
            'company_id' => $company->id,
            'name' => 'Bolivia',
            'iso_code' => 'BO',
            'is_active' => true,
        ]);
        $channel = ReservationChannel::query()->create([
            'company_id' => $company->id,
            'name' => 'Walk-in',
            'slug' => 'walk-in',
            'type' => 'direct',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'QR',
            'is_active' => true,
        ]);
        ExchangeRate::query()->create([
            'company_id' => $company->id,
            'from_currency' => 'USD',
            'to_currency' => 'BOB',
            'rate' => 6.96,
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        return [
            $user,
            $this->privateSpace($company, 'Casa 1'),
            $this->privateSpace($company, 'Casa 2'),
            $country,
            $channel,
            $paymentMethod,
        ];
    }

    private function privateSpace(Company $company, string $name): Space
    {
        return Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::query()->firstOrCreate(
                ['slug' => 'privado'],
                ['name' => 'Privado', 'is_active' => true],
            )->id,
            'private_space_type_id' => PrivateSpaceType::query()->firstOrCreate(
                ['slug' => 'casa'],
                ['name' => 'Casa', 'is_active' => true],
            )->id,
            'title' => $name,
            'name' => $name,
            'max_capacity' => 4,
            'status' => 'active',
            'created_by' => null,
        ]);
    }
}

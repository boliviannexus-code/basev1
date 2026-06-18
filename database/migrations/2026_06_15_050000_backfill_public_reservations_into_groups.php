<?php

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reservations')
            ->whereNull('reservation_group_id')
            ->whereNotNull('user_id')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->each(function ($reservation): void {
                $status = match ($reservation->status) {
                    'payment_under_review' => 'payment_under_review',
                    'confirmed' => 'confirmed',
                    'checked_in' => 'checked_in',
                    'cancelled', 'rejected', 'expired' => 'cancelled',
                    default => 'pending_payment',
                };
                $paymentStatus = $reservation->payment_status === 'validated' ? 'validated' : 'pending';
                $now = now();

                $groupId = DB::table('reservation_groups')->insertGetId([
                    'company_id' => $reservation->company_id,
                    'reservation_channel_id' => $reservation->reservation_channel_id,
                    'code' => 'RSG-MIG-'.str_pad((string) $reservation->id, 8, '0', STR_PAD_LEFT),
                    'guest_name' => $reservation->guest_name,
                    'guest_email' => $reservation->guest_email,
                    'guest_phone' => $reservation->guest_phone,
                    'guest_document' => $reservation->guest_document,
                    'check_in' => $reservation->check_in,
                    'check_out' => $reservation->check_out,
                    'nights' => $reservation->nights,
                    'guests' => $reservation->guests,
                    'subtotal_amount' => $reservation->subtotal_amount,
                    'total_amount' => $reservation->total_amount,
                    'advance_amount' => $reservation->advance_amount,
                    'balance_amount' => $reservation->balance_amount,
                    'currency' => $reservation->currency ?: 'BOB',
                    'status' => $status,
                    'payment_status' => $paymentStatus,
                    'payment_method' => $reservation->payment_method,
                    'payment_reference' => $reservation->payment_reference,
                    'notes' => $reservation->guest_notes,
                    'created_by' => null,
                    'created_at' => $reservation->created_at ?: $now,
                    'updated_at' => $now,
                ]);

                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update([
                        'reservation_group_id' => $groupId,
                        'updated_at' => $now,
                    ]);

                $statementId = DB::table('account_statements')->insertGetId([
                    'company_id' => $reservation->company_id,
                    'stay_id' => null,
                    'reservation_id' => null,
                    'reservation_group_id' => $groupId,
                    'currency' => $reservation->currency ?: 'BOB',
                    'subtotal' => $reservation->subtotal_amount,
                    'discount_total' => 0,
                    'extra_charges_total' => 0,
                    'payments_total' => 0,
                    'balance' => $reservation->subtotal_amount,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $checkIn = CarbonImmutable::parse($reservation->check_in);
                $checkOut = CarbonImmutable::parse($reservation->check_out)->subDay();

                foreach (CarbonPeriod::create($checkIn, $checkOut) as $date) {
                    DB::table('account_statement_items')->insert([
                        'company_id' => $reservation->company_id,
                        'account_statement_id' => $statementId,
                        'stay_id' => null,
                        'reservation_id' => $reservation->id,
                        'reservation_group_id' => $groupId,
                        'date' => $date->toDateString(),
                        'type' => 'lodging_night',
                        'description' => 'Reserva migrada - Noche '.$date->toDateString(),
                        'quantity' => 1,
                        'unit_price' => $reservation->price_per_person,
                        'total' => $reservation->price_per_person,
                        'currency' => $reservation->currency ?: 'BOB',
                        'source' => 'system',
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        $groupIds = DB::table('reservation_groups')
            ->where('code', 'like', 'RSG-MIG-%')
            ->pluck('id');

        DB::table('reservations')
            ->whereIn('reservation_group_id', $groupIds)
            ->update(['reservation_group_id' => null]);
        DB::table('account_statement_items')->whereIn('reservation_group_id', $groupIds)->delete();
        DB::table('account_statements')->whereIn('reservation_group_id', $groupIds)->delete();
        DB::table('reservation_groups')->whereIn('id', $groupIds)->delete();
    }
};

<?php

namespace App\Support;

use App\Models\CheckInGroup;
use App\Models\Stay;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Models\Audit;

class AuditPresenter
{
    private const LABELS = [
        'company_id' => 'Empresa', 'code' => 'Código', 'check_in_group_id' => 'Check-in',
        'space_id' => 'Alojamiento', 'space_room_id' => 'Habitación', 'room_bed_unit_id' => 'Cama',
        'holder_guest_id' => 'Huésped titular', 'main_guest_id' => 'Huésped principal',
        'check_in_date' => 'Fecha de ingreso', 'check_out_date' => 'Fecha de salida',
        'checked_out_at' => 'Fecha y hora de salida', 'people_count' => 'Personas', 'total_people' => 'Personas',
        'nights' => 'Noches', 'price_per_night_bob' => 'Precio por noche (BOB)', 'price_per_night_usd' => 'Precio por noche (USD)',
        'amount_bob' => 'Importe cobrado (BOB)', 'amount_original' => 'Importe original', 'amount' => 'Importe (BOB)',
        'currency_original' => 'Moneda original', 'currency' => 'Moneda', 'exchange_rate' => 'Tipo de cambio',
        'payment_method_id' => 'Método de pago', 'payment_method_name' => 'Método de pago', 'sale_id' => 'Venta', 'total' => 'Total', 'receipt_number' => 'Comprobante', 'reference' => 'Referencia',
        'stay_id' => 'Estancia', 'reservation_group_id' => 'Reserva', 'space_cash_register_id' => 'Caja', 'cash_register_id' => 'Caja',
        'user_id' => 'Responsable de caja', 'created_by' => 'Registrado por', 'status' => 'Estado',
        'detail' => 'Detalle', 'responsible_name' => 'Responsable', 'quantity' => 'Cantidad', 'notes' => 'Notas',
        'received_at' => 'Fecha de ingreso', 'spent_at' => 'Fecha de egreso', 'breakfast_included' => 'Desayuno incluido',
        'opening_amount' => 'Monto de apertura', 'closing_amount' => 'Efectivo contado', 'name' => 'Nombre', 'email' => 'Correo',
    ];

    public function label(string $field): string
    {
        return self::LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    public function summary(Audit $audit): string
    {
        $values = $audit->new_values ?? [];
        $prefix = match (true) {
            $audit->auditable_type === Stay::class && $audit->event === 'created' => 'Check-in registrado',
            $audit->auditable_type === CheckInGroup::class && $audit->event === 'created' => 'Check-in creado',
            default => '',
        };
        $amount = $values['amount_bob'] ?? $values['amount'] ?? null;
        if ($amount !== null && is_numeric($amount)) {
            $prefix = 'Importe: '.number_format((float) $amount, 2, '.', ',').' BOB';
        }
        $fields = collect(array_unique(array_merge(array_keys($audit->old_values ?? []), array_keys($values))))
            ->take($prefix ? 2 : 4)->map(fn ($field) => $this->label($field))->implode(', ');

        return collect([$prefix, $fields])->filter()->implode(' · ') ?: 'Sin cambios registrados';
    }

    public function value(string $field, mixed $value, ?int $companyId): string
    {
        if ($value === null || $value === '') return '—';
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '—';
        if (is_bool($value) || $field === 'breakfast_included') return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
        $states = ['occupied' => 'Ocupada', 'checked_in' => 'Check-in realizado', 'checked_out' => 'Check-out realizado', 'cancelled' => 'Anulado', 'active' => 'Activo', 'open' => 'Abierta', 'closed' => 'Cerrada'];
        if ($field === 'status') return $states[$value] ?? (string) $value;
        if (in_array($field, ['amount_bob', 'amount', 'price_per_night_bob', 'opening_amount', 'closing_amount'], true) && is_numeric($value)) return number_format((float) $value, 2, '.', ',').' BOB';
        $lookups = [
            'space_id' => ['spaces', 'title'], 'space_room_id' => ['space_rooms', 'name'],
            'room_bed_unit_id' => ['room_bed_units', 'label'], 'payment_method_id' => ['payment_methods', 'name'],
            'user_id' => ['users', 'name'], 'created_by' => ['users', 'name'],
            'check_in_group_id' => ['check_in_groups', 'code'], 'reservation_group_id' => ['reservation_groups', 'code'],
        ];
        if ($field === 'company_id' && is_numeric($value)) {
            return $companyId && (int) $value === $companyId
                ? (DB::table('companies')->where('id', $companyId)->value('name') ?: 'Empresa no disponible')
                : 'Empresa no disponible';
        }
        if ($companyId && in_array($field, ['cash_register_id', 'space_cash_register_id'], true) && is_numeric($value)) {
            $table = $field === 'cash_register_id' ? 'cash_registers' : 'space_cash_registers';
            $owner = DB::table($table)->leftJoin('users', 'users.id', '=', $table.'.user_id')
                ->where($table.'.company_id', $companyId)->where($table.'.id', $value)->value('users.name');
            return $owner ? 'Caja de '.$owner : 'Caja no disponible';
        }
        if ($companyId && $field === 'stay_id' && is_numeric($value)) {
            $stay = DB::table('stays')->leftJoin('spaces', 'spaces.id', '=', 'stays.space_id')
                ->leftJoin('space_rooms', 'space_rooms.id', '=', 'stays.space_room_id')
                ->where('stays.company_id', $companyId)->where('stays.id', $value)
                ->first(['spaces.title as space_name', 'space_rooms.name as room_name']);
            $resource = $stay ? collect([$stay->space_name, $stay->room_name])->filter()->implode(' / ') : '';
            return $resource ?: 'Estancia no disponible';
        }
        if ($companyId && in_array($field, ['holder_guest_id', 'main_guest_id'], true) && is_numeric($value)) {
            $guest = DB::table('guests')->where('company_id', $companyId)->where('id', $value)->first(['first_name', 'last_name']);
            return $guest ? trim($guest->first_name.' '.$guest->last_name) : 'Huésped no disponible';
        }
        if ($companyId && isset($lookups[$field]) && is_numeric($value)) {
            [$table, $column] = $lookups[$field];
            $name = DB::table($table)->where('company_id', $companyId)->where('id', $value)->value($column);
            return $name ?: 'Información no disponible';
        }
        if (str_ends_with($field, '_id')) return 'Información relacionada no disponible';
        return (string) $value;
    }

    public function context(Audit $audit): array
    {
        $companyId = $audit->company_id ? (int) $audit->company_id : null;
        $company = $companyId ? DB::table('companies')->where('id', $companyId)->value('name') : null;
        $values = ($audit->new_values ?? []) + ($audit->old_values ?? []);
        $roomId = $values['space_room_id'] ?? null;
        $current = false;
        if (! $roomId && $companyId) {
            $stayId = $audit->auditable_type === Stay::class ? $audit->auditable_id : ($values['stay_id'] ?? null);
            if ($stayId) {
                $roomId = DB::table('stays')->where('company_id', $companyId)->where('id', $stayId)->value('space_room_id');
                $current = (bool) $roomId;
            }
        }
        $room = $roomId ? $this->value('space_room_id', $roomId, $companyId) : null;
        return ['company' => $company ?: ($companyId ? 'Empresa no disponible' : 'Administración global'), 'room' => $room, 'room_is_current' => $current];
    }
}


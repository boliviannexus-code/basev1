<?php

use App\Models\Player;
use Illuminate\Support\Facades\Storage;

if (! function_exists('player_photo_url')) {
    function player_photo_url(?Player $player): ?string
    {
        if (! $player || blank($player->photo_path)) {
            return null;
        }

        return Storage::disk((string) config('player_media.photos.disk', 'public'))->url($player->photo_path);
    }
}

if (! function_exists('money_format_decimal')) {
    function money_format_decimal(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }
}

if (! function_exists('role_label')) {
    function role_label(string $name): string
    {
        $labels = [
            'admin' => 'Administrador',
            'super_admin' => 'Super administrador',
            'manager' => 'Gerente',
            'viewer' => 'Visualizador',
        ];

        return $labels[$name] ?? str($name)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('permission_module_label')) {
    function permission_module_label(string $module): string
    {
        $labels = [
            'dashboard' => 'Panel principal',
            'users' => 'Usuarios',
            'fingerprint-templates' => 'Huellas',
            'roles' => 'Roles',
            'permissions' => 'Permisos',
            'companies' => 'Ligas deportivas',
            'seasons' => 'Gestiones',
            'divisions' => 'Divisiones',
            'teams' => 'Equipos',
            'players' => 'Jugadores',
            'tournaments' => 'Torneos',
            'tournament-registrations' => 'Inscripciones',
            'player-habilitations' => 'Habilitaciones',
            'categories' => 'Categorias',
            'audits' => 'Auditoria',
        ];

        return $labels[$module] ?? str($module)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('player_team_status_label')) {
    function player_team_status_label(string $status): string
    {
        return [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
        ][$status] ?? str($status)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('habilitation_status_label')) {
    function habilitation_status_label(string $status): string
    {
        return [
            'enabled' => 'Habilitado',
            'disabled' => 'Retirado',
        ][$status] ?? str($status)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('permission_action_label')) {
    function permission_action_label(string $action): string
    {
        $labels = [
            'view' => 'Ver',
            'create' => 'Crear',
            'edit' => 'Editar',
            'update' => 'Actualizar',
            'delete' => 'Eliminar',
            'restore' => 'Restaurar',
            'change-password' => 'Cambiar contrasena',
            'assign-roles' => 'Asignar roles',
            'assign-permissions' => 'Asignar permisos',
            'approve-updates' => 'Aprobar ediciones',
            'manage' => 'Administrar',
        ];

        return $labels[$action] ?? str($action)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('permission_label')) {
    function permission_label(string $name): string
    {
        if (! str_contains($name, '.')) {
            return permission_action_label($name);
        }

        [$module, $action] = explode('.', $name, 2);

        return permission_module_label($module).': '.permission_action_label($action);
    }
}

if (! function_exists('sports_status_label')) {
    function sports_status_label(string $status): string
    {
        return [
            'planned' => 'Planificado',
            'active' => 'Activo',
            'closed' => 'Cerrado',
        ][$status] ?? str($status)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('sports_status_tone')) {
    function sports_status_tone(string $status): string
    {
        return [
            'planned' => 'secondary',
            'active' => 'success',
            'closed' => 'dark',
        ][$status] ?? 'secondary';
    }
}

if (! function_exists('registration_status_label')) {
    function registration_status_label(string $status): string
    {
        return [
            'registered' => 'Inscrito',
            'withdrawn' => 'Retirado',
        ][$status] ?? str($status)->replace(['-', '_'], ' ')->headline()->toString();
    }
}

if (! function_exists('registration_status_tone')) {
    function registration_status_tone(string $status): string
    {
        return [
            'registered' => 'success',
            'withdrawn' => 'secondary',
        ][$status] ?? 'secondary';
    }
}

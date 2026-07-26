<?php

namespace App\Support;

class PermissionCatalog
{
    public static function modules(): array
    {
        return [
            'dashboard' => ['label' => 'Panel principal', 'description' => 'Acceso al resumen inicial de la empresa.', 'order' => 10],
            'companies' => ['label' => 'Empresa', 'description' => 'Datos administrativos y gestión de empresas.', 'order' => 20],
            'company-public-profile' => ['label' => 'Perfil público', 'description' => 'Contenido público, contacto, imágenes y visibilidad de la empresa.', 'order' => 30],
            'spaces' => ['label' => 'Alojamientos, paquetes y servicios', 'description' => 'Registro de espacios privados o compartidos, paquetes comerciales y servicios incluidos.', 'order' => 40],
            'availability' => ['label' => 'Disponibilidad', 'description' => 'Calendario, precios y estados disponibles para reserva.', 'order' => 50],
            'occupancy' => ['label' => 'Ocupabilidad y estadías', 'description' => 'Ocupación, check-in, check-out, bloqueos y cargos.', 'order' => 60],
            'reservations' => ['label' => 'Reservas', 'description' => 'Consulta y administración de reservas entrantes.', 'order' => 70],
            'reservation-settings' => ['label' => 'Configuración de reservas', 'description' => 'Porcentajes y reglas comerciales de reserva.', 'order' => 80],
            'reservation-channels' => ['label' => 'Canales de reserva', 'description' => 'Canales de venta y sus comisiones.', 'order' => 90],
            'space-cash' => ['label' => 'Cajas de alojamiento', 'description' => 'Operación e historial de cajas vinculadas a alojamientos.', 'order' => 100],
            'pos' => ['label' => 'Punto de venta', 'description' => 'Acceso operativo al módulo de ventas.', 'order' => 110],
            'sales' => ['label' => 'Ventas', 'description' => 'Consulta y anulación de ventas.', 'order' => 120],
            'reports' => ['label' => 'Reportes', 'description' => 'Consulta e impresión de reportes operativos.', 'order' => 130],
            'business-intelligence' => ['label' => 'Business Intelligence', 'description' => 'Indicadores avanzados, pronósticos y rentabilidad hotelera.', 'order' => 140],
            'payment-methods' => ['label' => 'Métodos de pago', 'description' => 'Catálogo de medios de pago disponibles.', 'order' => 150],
            'point-of-sales' => ['label' => 'Configuración de puntos de venta', 'description' => 'Administración de cajas y puntos de venta.', 'order' => 160],
            'countries' => ['label' => 'Países', 'description' => 'Catálogo de países utilizado por huéspedes y reservas.', 'order' => 170],
            'exchange-rates' => ['label' => 'Tipos de cambio', 'description' => 'Registro de cotizaciones monetarias.', 'order' => 180],
            'extra-charge-categories' => ['label' => 'Categorías de cargos extra', 'description' => 'Conceptos adicionales cobrables durante una estadía.', 'order' => 190],
            'accommodation-catalogs' => ['label' => 'Catálogos globales de alojamiento', 'description' => 'Tipos de camas, baños, servicios y catálogos globales.', 'order' => 200],
            'users' => ['label' => 'Usuarios', 'description' => 'Cuentas de acceso vinculadas a la empresa.', 'order' => 210],
            'roles' => ['label' => 'Roles', 'description' => 'Perfiles de acceso y asignación de permisos.', 'order' => 220],
            'permissions' => ['label' => 'Permisos técnicos', 'description' => 'Catálogo técnico de permisos del sistema.', 'order' => 230],
            'audits' => ['label' => 'Auditoría', 'description' => 'Historial de cambios y acciones relevantes.', 'order' => 240],
            'database-backups' => ['label' => 'Respaldos de base de datos', 'description' => 'Exportación, validación y restauración verificada de datos.', 'order' => 250],
        ];
    }

    public static function permissionDescriptions(): array
    {
        return [
            'company-public-profile.manage' => 'Ver y modificar el perfil público de la empresa.',
            'spaces.approve' => 'Revisar, aprobar o solicitar correcciones a alojamientos de cualquier empresa.',
            'accommodation-catalogs.manage' => 'Administrar los catálogos globales usados por todos los alojamientos.',
            'occupancy.manage' => 'Modificar ocupación, realizar check-in/check-out y gestionar bloqueos.',
            'space-cash.access' => 'Abrir, operar y cerrar cajas de alojamientos.',
            'space-cash.view' => 'Consultar el historial y detalle de cajas.',
            'pos.access' => 'Utilizar el punto de venta para registrar cobros.',
            'sales.void' => 'Anular ventas registradas.',
            'reports.view' => 'Consultar reportes operativos con filtros.',
            'reports.print' => 'Generar reportes imprimibles en PDF.',
            'business-intelligence.view' => 'Consultar indicadores avanzados de Business Intelligence.',
            'roles.assign-permissions' => 'Cambiar los permisos que pertenecen a cada rol.',
            'users.assign-roles' => 'Asignar o retirar roles a usuarios.',
            'database-backups.manage' => 'Exportar respaldos, validarlos y restaurar la base de datos.',
        ];
    }

    public static function module(string $module): array
    {
        return self::modules()[$module] ?? [
            'label' => str($module)->replace(['-', '_'], ' ')->headline()->toString(),
            'description' => 'Permisos relacionados con este módulo.',
            'order' => 999,
        ];
    }

    public static function moduleLabel(string $module): string
    {
        return self::module($module)['label'];
    }

    public static function moduleDescription(string $module): string
    {
        return self::module($module)['description'];
    }

    public static function moduleOrder(string $module): int
    {
        return self::module($module)['order'];
    }

    public static function permissionDescription(string $permission): string
    {
        return self::permissionDescriptions()[$permission]
            ?? match (str($permission)->after('.')->toString()) {
                'view' => 'Consultar la información de este módulo.',
                'create' => 'Crear nuevos registros en este módulo.',
                'edit', 'update' => 'Modificar registros existentes en este módulo.',
                'delete' => 'Eliminar registros de este módulo.',
                'restore' => 'Restaurar registros eliminados.',
                'manage' => 'Administrar todas las funciones operativas de este módulo.',
                default => 'Habilita esta acción dentro del módulo.',
            };
    }
}

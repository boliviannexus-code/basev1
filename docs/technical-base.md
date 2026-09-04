# Inventario POS - Base Tecnica

## Stack

- PHP 8.3+
- Laravel 13
- PostgreSQL 17
- Docker/Sail
- Blade + Bootstrap 5
- CoreUI
- Laravel Sanctum
- Spatie Laravel Permission

## Arquitectura inicial

La base sigue MVC con controladores delgados, validacion por Form Requests, salida API mediante Resources, reglas de autorizacion con Policies, logica de negocio en Services y acceso a datos en Repositories.

```txt
app/
  Http/Controllers/Web
  Http/Controllers/Api/V1
  Http/Requests
  Http/Resources
  Services
  Repositories
  Policies
  Traits
  Enums
  Helpers
resources/views/
  components/admin
  components/ui
  layouts
```

## Modulos implementados

- Autenticacion web inicial.
- Autenticacion API por token Sanctum.
- Roles y permisos iniciales.
- Categorias con CRUD web y API.
- Productos con CRUD web y API.
- Dashboard administrativo base.
- Layout administrativo CoreUI reutilizable.
- Componentes Blade para sidebar, header, alerts, cards, stats, tablas y formularios.

## Frontend administrativo

El panel usa CoreUI sobre Bootstrap 5 mediante Vite. La estructura esta preparada para Livewire futuro porque el layout centraliza el shell visual y los componentes Blade encapsulan piezas repetibles.

- `resources/views/layouts/admin.blade.php`: shell administrativo.
- `resources/views/components/admin/sidebar.blade.php`: sidebar responsive.
- `resources/views/components/admin/header.blade.php`: navbar superior.
- `resources/views/components/admin/flash.blade.php`: alertas del sistema.
- `resources/views/components/ui/card.blade.php`: tarjeta base.
- `resources/views/components/ui/table-card.blade.php`: tabla responsive.
- `resources/views/components/ui/form-panel.blade.php`: panel de formulario.
- `resources/views/components/ui/stat-card.blade.php`: metrica de dashboard.

## Usuario inicial

```txt
Email: admin@example.com
Password: password
Rol: super_admin
```

## Formato API

Todas las respuestas controladas usan:

```json
{
  "success": true,
  "message": "Operación realizada correctamente",
  "data": {}
}
```

## Rutas principales

Web:

- `GET /login`
- `POST /login`
- `POST /logout`
- `GET /`
- `resource /categories`
- `resource /products`

API:

- `POST /api/v1/auth/login`
- `POST /api/v1/auth/logout`
- `apiResource /api/v1/categories`
- `apiResource /api/v1/products`

## Tablas base futuras

Se dejaron migraciones iniciales para:

- branches
- warehouses
- suppliers
- customers
- purchases
- purchase_details
- sales
- sale_details
- inventory_movements
- cash_registers
- payment_methods

## Siguiente paso recomendado

Implementar stock por almacen y Kardex real:

- `stocks`
- movimientos transaccionales
- entradas por compra
- salidas por venta
- ajustes
- transferencias entre almacenes

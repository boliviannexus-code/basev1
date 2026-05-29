# Migracion legacy Mejillones

Proceso local/staging para migrar el dump MariaDB antiguo hacia NexGol con auditoria.

## 1. Levantar servicios

```bash
./vendor/bin/sail up -d
```

Si el servicio `legacy_mysql` fue agregado despues de tener Sail levantado:

```bash
docker compose up -d legacy_mysql
```

## 2. Restaurar dump antiguo

```bash
docker compose exec -T legacy_mysql mariadb \
  -ulegacy -plegacy nexucjns_mejillones \
  < "/home/pachekurt/nexucjns_mejillones (1).sql"
```

## 3. Ejecutar migraciones de auditoria

```bash
./vendor/bin/sail artisan migrate
```

## 4. Backup previo de NexGol

```bash
./vendor/bin/sail artisan legacy:backup-current
```

## 5. Analisis y dry-run

```bash
./vendor/bin/sail artisan legacy:analyze-mejillones \
  --company-id=ID \
  --dump="/home/pachekurt/nexucjns_mejillones (1).sql"

./vendor/bin/sail artisan legacy:import-mejillones \
  --company-id=ID \
  --dump="/home/pachekurt/nexucjns_mejillones (1).sql" \
  --dry-run
```

Los reportes quedan en `storage/app/private/legacy-imports`.

## 6. Importacion real

```bash
./vendor/bin/sail artisan legacy:import-mejillones \
  --company-id=ID \
  --dump="/home/pachekurt/nexucjns_mejillones (1).sql" \
  --commit
```

## 7. Rollback por lote

```bash
./vendor/bin/sail artisan legacy:rollback-mejillones --batch=ID
```

El rollback solo elimina registros creados por el lote. Los registros existentes que fueron reutilizados no se eliminan.

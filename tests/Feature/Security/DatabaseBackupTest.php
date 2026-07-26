<?php

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_creates_sql_file_and_restores_verified_data(): void
    {
        $service = app(DatabaseBackupService::class);

        $manageBackups = Permission::findOrCreate('database-backups.manage');
        $viewReports = Permission::findOrCreate('reports.view');
        Role::findOrCreate('admin')->givePermissionTo([$manageBackups, $viewReports]);
        Role::findOrCreate('manager')->givePermissionTo([$viewReports, $manageBackups]);

        User::factory()->create(['name' => 'Operador respaldo']);
        $category = Category::factory()->create(['name' => 'Bebidas']);
        $unit = MeasurementUnit::factory()->create(['name' => 'Unidad', 'abbreviation' => 'u']);
        Product::factory()
            ->for($category)
            ->for($unit, 'measurementUnit')
            ->create(['name' => 'Agua mineral']);

        $filename = $service->createSqlBackup();

        Storage::disk('local')->assertExists('private/database-backups/'.$filename);
        $this->assertStringEndsWith('.sql', $filename);

        Product::query()->delete();
        Category::query()->delete();
        MeasurementUnit::query()->delete();

        $this->assertDatabaseMissing('products', ['name' => 'Agua mineral']);

        $summary = $service->restoreStoredSql($filename);

        $this->assertTrue($summary['valid']);
        $this->assertDatabaseHas('users', ['name' => 'Operador respaldo']);
        $this->assertDatabaseHas('products', ['name' => 'Agua mineral']);
        $this->assertDatabaseHas('categories', ['name' => 'Bebidas']);
        $this->assertDatabaseHas('measurement_units', ['abbreviation' => 'u']);
        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => $manageBackups->id,
            'role_id' => Role::findByName('admin')->id,
        ]);
        $this->assertDatabaseHas('role_has_permissions', [
            'permission_id' => $viewReports->id,
            'role_id' => Role::findByName('manager')->id,
        ]);

        Storage::disk('local')->delete('private/database-backups/'.$filename);
    }

    public function test_database_backup_rejects_incomplete_generated_sql_without_changing_current_data(): void
    {
        $service = app(DatabaseBackupService::class);

        Category::factory()->create(['name' => 'Dato actual']);
        $sql = preg_replace('/^-- NIDO_BACKUP_TABLE: categories \d+ [a-f0-9]{64}$/m', '-- NIDO_BACKUP_TABLE: categories 999 '.str_repeat('a', 64), $service->exportSql());

        try {
            $service->restoreUploadedSql(UploadedFile::fake()->createWithContent('respaldo.sql', (string) $sql));
            $this->fail('The incomplete backup should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('categories', implode(' ', $exception->errors()['backup']));
        }

        $this->assertDatabaseHas('categories', ['name' => 'Dato actual']);
    }

    public function test_database_backup_rejects_truncated_generated_sql_before_truncating_tables(): void
    {
        $service = app(DatabaseBackupService::class);

        Category::factory()->create(['name' => 'Dato protegido']);
        $sql = $service->exportSql();
        $truncatedSql = str_replace("\n-- NIDO_BACKUP_END\n", "\n", $sql);

        try {
            $service->restoreUploadedSql(UploadedFile::fake()->createWithContent('respaldo.sql', $truncatedSql));
            $this->fail('The truncated backup should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('marca final', implode(' ', $exception->errors()['backup']));
        }

        $this->assertDatabaseHas('categories', ['name' => 'Dato protegido']);
    }

    public function test_database_backup_rolls_back_when_post_restore_validation_fails(): void
    {
        $service = app(DatabaseBackupService::class);

        Category::factory()->create(['name' => 'Dato intacto']);
        $sql = preg_replace('/^-- NIDO_BACKUP_TABLE: categories \d+ [a-f0-9]{64}$/m', '-- NIDO_BACKUP_TABLE: categories 999 '.str_repeat('a', 64), $service->exportSql());

        try {
            $service->restoreUploadedSql(UploadedFile::fake()->createWithContent('respaldo.sql', (string) $sql));
            $this->fail('The invalid backup should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('categories', implode(' ', $exception->errors()['backup']));
        }

        $this->assertDatabaseHas('categories', ['name' => 'Dato intacto']);
    }

    public function test_database_backup_list_download_upload_restore_and_delete_routes(): void
    {
        Permission::findOrCreate('database-backups.manage');
        $user = User::factory()->create();
        $user->givePermissionTo('database-backups.manage');
        Category::factory()->create(['name' => 'Ruta SQL']);
        $existingFiles = Storage::disk('local')->files('private/database-backups');

        $this->actingAs($user)
            ->post(route('database-backups.store'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $newFiles = array_values(array_diff(Storage::disk('local')->files('private/database-backups'), $existingFiles));
        $this->assertCount(1, $newFiles);
        $filename = basename($newFiles[0]);

        $this->actingAs($user)
            ->get(route('database-backups.index'))
            ->assertOk()
            ->assertSee($filename);

        $this->actingAs($user)
            ->get(route('database-backups.download', $filename))
            ->assertOk();

        Category::query()->delete();

        $this->actingAs($user)
            ->post(route('database-backups.restore', $filename), ['confirm_restore' => '1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['name' => 'Ruta SQL']);

        $sql = Storage::disk('local')->get('private/database-backups/'.$filename);
        Category::query()->delete();

        $this->actingAs($user)
            ->post(route('database-backups.restore-upload'), [
                'backup' => UploadedFile::fake()->createWithContent('externo.sql', $sql),
                'confirm_restore' => '1',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', ['name' => 'Ruta SQL']);

        $this->actingAs($user)
            ->delete(route('database-backups.destroy', $filename))
            ->assertRedirect()
            ->assertSessionHas('success');

        Storage::disk('local')->assertMissing('private/database-backups/'.$filename);
    }

    public function test_database_backup_routes_require_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('database-backups.index'))
            ->assertForbidden();

        Permission::findOrCreate('database-backups.manage');
        $user->givePermissionTo('database-backups.manage');

        $this->actingAs($user)
            ->get(route('database-backups.index'))
            ->assertOk()
            ->assertSee('Respaldos de base de datos');
    }
}

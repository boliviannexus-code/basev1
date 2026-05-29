<?php

namespace Tests\Feature\Legacy;

use Tests\TestCase;

class LegacyImportCommandTest extends TestCase
{
    public function test_import_command_requires_company_id(): void
    {
        $this
            ->artisan('legacy:import-mejillones --dry-run')
            ->expectsOutput('Debes indicar --company-id=ID.')
            ->assertFailed();
    }

    public function test_analyze_command_requires_company_id(): void
    {
        $this
            ->artisan('legacy:analyze-mejillones')
            ->expectsOutput('Debes indicar --company-id=ID.')
            ->assertFailed();
    }
}

<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class MoneyComponentTest extends TestCase
{
    public function test_it_displays_bob_with_its_usd_reference(): void
    {
        $html = Blade::render('<x-ui.money :amount="696" :exchange-rate="6.96" />');

        $this->assertStringContainsString('696.00 BOB', $html);
        $this->assertStringContainsString('≈ 100.00 USD', $html);
    }

    public function test_it_displays_usd_with_its_bob_reference(): void
    {
        $html = Blade::render('<x-ui.money :amount="100" currency="USD" :exchange-rate="6.96" />');

        $this->assertStringContainsString('100.00 USD', $html);
        $this->assertStringContainsString('≈ 696.00 BOB', $html);
    }
}

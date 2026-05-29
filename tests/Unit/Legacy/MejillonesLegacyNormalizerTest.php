<?php

namespace Tests\Unit\Legacy;

use App\Models\Player;
use App\Services\Legacy\MejillonesLegacyNormalizer;
use PHPUnit\Framework\TestCase;

class MejillonesLegacyNormalizerTest extends TestCase
{
    public function test_generates_traceable_ci_when_legacy_player_has_no_document(): void
    {
        $normalizer = new MejillonesLegacyNormalizer;

        [$ci, $normalized, $generated] = $normalizer->ci('0', 123);

        $this->assertSame('LEGACY-123', $ci);
        $this->assertSame(Player::normalizeCi('LEGACY-123'), $normalized);
        $this->assertTrue($generated);
    }

    public function test_invalid_legacy_dates_use_fallback(): void
    {
        $normalizer = new MejillonesLegacyNormalizer;

        $this->assertSame('1900-01-01', $normalizer->date('0000-00-00', '1900-01-01'));
        $this->assertSame('2017-04-21', $normalizer->date('2017-04-21 00:00:00'));
    }

    public function test_notes_omit_empty_legacy_values(): void
    {
        $normalizer = new MejillonesLegacyNormalizer;

        $notes = $normalizer->notes([
            'legacy_id' => 10,
            'empty' => '',
            'zero' => '0',
            'name' => 'MEJILLONES',
        ]);

        $this->assertStringContainsString('legacy_id: 10', $notes);
        $this->assertStringContainsString('name: MEJILLONES', $notes);
        $this->assertStringNotContainsString('empty:', $notes);
        $this->assertStringNotContainsString('zero:', $notes);
    }
}

<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase
{
    public function test_autoloader_maps_the_plugin_namespace(): void
    {
        self::assertTrue(
            class_exists(\AcsCourier\Support\Version::class),
            'Expected AcsCourier\\Support\\Version to autoload from src/'
        );
    }
}

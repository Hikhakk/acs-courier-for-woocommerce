<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Support;

use AcsCourier\Support\Requirements;
use PHPUnit\Framework\TestCase;

final class RequirementsTest extends TestCase
{
    public function test_a_supported_stack_is_satisfied(): void
    {
        $r = new Requirements('8.1.0', '6.5', '9.0.0');
        self::assertTrue($r->isSatisfied());
        self::assertSame([], $r->unmet());
    }

    public function test_old_php_is_reported(): void
    {
        $r = new Requirements('7.4.0', '6.5', '9.0.0');
        self::assertFalse($r->isSatisfied());
        self::assertStringContainsString('PHP', $r->unmet()[0]);
    }

    public function test_missing_woocommerce_is_reported(): void
    {
        $r = new Requirements('8.1.0', '6.5', null);
        self::assertFalse($r->isSatisfied());
        self::assertStringContainsString('WooCommerce', implode(' ', $r->unmet()));
    }

    public function test_every_unmet_requirement_is_listed_not_just_the_first(): void
    {
        $r = new Requirements('7.4.0', '5.9', null);
        self::assertCount(3, $r->unmet());
    }
}

<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\Throttle;
use PHPUnit\Framework\TestCase;

final class ThrottleTest extends TestCase
{
    public function test_it_sleeps_once_the_per_second_ceiling_is_reached(): void
    {
        $now   = 1000.0;
        $slept = [];

        $throttle = new Throttle(
            3,
            static function (float $s) use (&$slept): void { $slept[] = $s; },
            static function () use (&$now): float { return $now; }
        );

        $throttle->acquire();
        $throttle->acquire();
        $throttle->acquire();
        self::assertSame([], $slept, 'First three calls are within budget.');

        $throttle->acquire();
        self::assertCount(1, $slept, 'Fourth call in the same second must wait.');
    }

    public function test_it_does_not_sleep_when_calls_are_spread_out(): void
    {
        $now   = 1000.0;
        $slept = [];

        $throttle = new Throttle(
            2,
            static function (float $s) use (&$slept): void { $slept[] = $s; },
            static function () use (&$now): float { return $now; }
        );

        $throttle->acquire();
        $now += 1.5;
        $throttle->acquire();
        $now += 1.5;
        $throttle->acquire();

        self::assertSame([], $slept);
    }
}

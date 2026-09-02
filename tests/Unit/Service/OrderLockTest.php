<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Service\OrderLock;
use PHPUnit\Framework\TestCase;

final class OrderLockTest extends TestCase
{
    private function lock(array &$store): OrderLock
    {
        return new OrderLock(
            static function (string $k) use (&$store) { return $store[$k] ?? false; },
            static function (string $k, $v) use (&$store): bool {
                if (isset($store[$k])) { return false; }
                $store[$k] = $v;
                return true;
            },
            static function (string $k) use (&$store): void { unset($store[$k]); }
        );
    }

    public function test_a_second_acquire_for_the_same_order_fails(): void
    {
        $store = [];
        $lock  = $this->lock($store);

        self::assertTrue($lock->acquire(42));
        self::assertFalse($lock->acquire(42), 'A concurrent create must be refused.');

        $lock->release(42);
        self::assertTrue($lock->acquire(42), 'Released locks can be re-acquired.');
    }

    public function test_it_reports_whether_an_order_is_locked(): void
    {
        $store = [];
        $lock  = $this->lock($store);

        self::assertFalse($lock->isLocked(7));
        $lock->acquire(7);
        self::assertTrue($lock->isLocked(7));
        $lock->release(7);
        self::assertFalse($lock->isLocked(7));
    }

    public function test_locks_are_per_order(): void
    {
        $store = [];
        $lock  = $this->lock($store);

        self::assertTrue($lock->acquire(1));
        self::assertTrue($lock->acquire(2));
    }
}

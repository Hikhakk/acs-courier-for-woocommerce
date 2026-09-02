<?php
/**
 * Prevents two concurrent requests creating two vouchers for one order.
 *
 * Injected callables keep this unit testable without WordPress; in production
 * they wrap add_option, whose INSERT is atomic.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

final class OrderLock
{
    private const PREFIX = 'acs_wc_lock_order_';

    /** @var callable */
    private $get;
    /** @var callable */
    private $add;
    /** @var callable */
    private $delete;

    public function __construct(?callable $get = null, ?callable $add = null, ?callable $delete = null)
    {
        $this->get    = $get ?? static function (string $key) {
            return get_option($key, false);
        };
        $this->add    = $add ?? static function (string $key, $value): bool {
            // add_option returns false when the key exists: an atomic INSERT.
            return add_option($key, $value, '', 'no');
        };
        $this->delete = $delete ?? static function (string $key): void {
            delete_option($key);
        };
    }

    public function acquire(int $orderId): bool
    {
        return (bool) ($this->add)(self::PREFIX . $orderId, time());
    }

    public function isLocked(int $orderId): bool
    {
        return false !== ($this->get)(self::PREFIX . $orderId);
    }

    public function release(int $orderId): void
    {
        ($this->delete)(self::PREFIX . $orderId);
    }
}

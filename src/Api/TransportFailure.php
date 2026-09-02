<?php
/**
 * Raised when a request never reached ACS at all.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * Raised when a request never reached ACS at all.
 */
final class TransportFailure extends \RuntimeException {

}

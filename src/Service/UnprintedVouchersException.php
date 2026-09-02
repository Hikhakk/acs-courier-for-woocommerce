<?php
/**
 * Raised when a pickup list cannot be issued because vouchers are unprinted.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

/**
 * Raised when a pickup list cannot be issued because vouchers are unprinted.
 *
 * ACS reports this as a successful call carrying a count, not as an error, so
 * it needs its own type to stop it being mistaken for success.
 */
final class UnprintedVouchersException extends \RuntimeException {

	/**
	 * Voucher numbers that still need printing.
	 *
	 * @var list<string>
	 */
	private array $vouchers;

	/**
	 * Records which vouchers are unprinted.
	 *
	 * @param list<string> $vouchers Voucher numbers that still need printing.
	 */
	public function __construct( array $vouchers ) {
		$this->vouchers = $vouchers;
		parent::__construct(
			sprintf(
				'The pickup list cannot be issued: %d voucher(s) have not been printed yet.',
				count( $vouchers )
			)
		);
	}

	/**
	 * Returns the unprinted voucher numbers.
	 *
	 * @return list<string>
	 */
	public function vouchers(): array {
		return $this->vouchers;
	}
}

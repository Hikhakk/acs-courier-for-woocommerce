<?php
/**
 * Splits a one-line address into street and number, which ACS wants separately.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

/**
 * Splits a one-line address into the street and number ACS wants separately.
 */
final class AddressSplitter {

	/**
	 * Splits an address into street and number.
	 *
	 * @param string $address One-line address.
	 * @return array{street:string,number:string}
	 */
	public static function split( string $address ): array {
		$normalised = trim( (string) preg_replace( '/\s+/u', ' ', $address ) );

		if ( '' === $normalised ) {
			return array(
				'street' => '',
				'number' => '',
			);
		}

		// A trailing token starting with a digit is the street number: 25, 12Α, 5-7, 45B.
		if ( 1 === preg_match( '/^(.*?)\s+(\d[\d\-\/]*[\p{L}]?)$/u', $normalised, $m ) ) {
			return array(
				'street' => trim( $m[1] ),
				'number' => $m[2],
			);
		}

		return array(
			'street' => $normalised,
			'number' => '',
		);
	}
}

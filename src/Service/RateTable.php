<?php
/**
 * A weight-banded shipping rate table.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Domain\Weight;

/**
 * A weight-banded shipping rate table.
 *
 * ACS_Price_Calculation does not support Cyprus, so Cypriot rates have to come
 * from the merchant's own contract rather than from the API.
 */
final class RateTable {

	/**
	 * Bands, each with max_kg, home and locker prices.
	 *
	 * @var list<array{max_kg:float,home:float,locker:float}>
	 */
	private array $bands;

	/**
	 * Charge per additional kilogram above the heaviest band.
	 *
	 * @var float
	 */
	private float $per_extra_kg;

	/**
	 * Order total at or above which shipping is free. Zero disables it.
	 *
	 * @var float
	 */
	private float $free_from;

	/**
	 * Builds the table.
	 *
	 * @param array<int,array{max_kg:float,home:float,locker:float}> $bands        Weight bands.
	 * @param float                                                  $per_extra_kg Charge per kilo above the top band.
	 * @param float                                                  $free_from    Free-shipping threshold; 0 disables.
	 */
	public function __construct( array $bands, float $per_extra_kg = 0.0, float $free_from = 0.0 ) {
		usort(
			$bands,
			static function ( array $a, array $b ): int {
				return $a['max_kg'] <=> $b['max_kg'];
			}
		);

		$this->bands        = array_values( $bands );
		$this->per_extra_kg = $per_extra_kg;
		$this->free_from    = $free_from;
	}

	/**
	 * Returns the shipping charge, or null when no band is configured.
	 *
	 * @param Weight $weight       Shipment weight.
	 * @param bool   $to_locker    Whether delivery is to a pickup point.
	 * @param float  $order_total  Order total, for the free-shipping threshold.
	 * @return float|null
	 */
	public function rate( Weight $weight, bool $to_locker, float $order_total = 0.0 ): ?float {
		if ( array() === $this->bands ) {
			return null;
		}

		if ( $this->free_from > 0.0 && $order_total >= $this->free_from ) {
			return 0.0;
		}

		$kg     = $weight->kilograms();
		$column = $to_locker ? 'locker' : 'home';

		foreach ( $this->bands as $band ) {
			if ( $kg <= $band['max_kg'] ) {
				return round( (float) $band[ $column ], 2 );
			}
		}

		// Above the heaviest band: top price plus a charge for each whole extra kilo,
		// rounding a part-kilo up the way carriers do.
		$top   = $this->bands[ count( $this->bands ) - 1 ];
		$extra = (int) ceil( $kg - $top['max_kg'] );

		return round( (float) $top[ $column ] + ( $extra * $this->per_extra_kg ), 2 );
	}
}

<?php
/**
 * A shipment weight, clamped to the bounds ACS accepts.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

/**
 * A shipment weight, clamped to the bounds ACS accepts.
 */
final class Weight {

	public const MIN_KG             = 0.5;
	public const MAX_KG             = 999.0;
	public const VOLUMETRIC_DIVISOR = 5000;

	/**
	 * Weight in kilograms.
	 *
	 * @var float
	 */
	private float $kilograms;

	/**
	 * __construct.
	 *
	 * @param float $kilograms Kilograms.
	 */
	private function __construct( float $kilograms ) {
		$this->kilograms = $kilograms;
	}

	/**
	 * From kilograms.
	 *
	 * @param float $kg Kg.
	 * @return self
	 */
	public static function fromKilograms( float $kg ): self {
		return new self( max( 0.0, $kg ) );
	}

	/**
	 * Volumetric.
	 *
	 * @param float $lengthCm Length cm.
	 * @param float $widthCm Width cm.
	 * @param float $heightCm Height cm.
	 * @return self
	 */
	public static function volumetric( float $lengthCm, float $widthCm, float $heightCm ): self {
		return new self( ( $lengthCm * $widthCm * $heightCm ) / self::VOLUMETRIC_DIVISOR );
	}

	/**
	 * Kilograms.
	 *
	 * @return float
	 */
	public function kilograms(): float {
		return $this->kilograms;
	}

	/**
	 * Is above maximum.
	 *
	 * @return bool
	 */
	public function isAboveMaximum(): bool {
		return $this->kilograms > self::MAX_KG;
	}

	/** Clamped and rounded exactly as ACS expects it on the wire. */
	public function forAcs(): float {
		$clamped = min( self::MAX_KG, max( self::MIN_KG, $this->kilograms ) );
		return round( $clamped, 2 );
	}

	/**
	 * Is heavier than.
	 *
	 * @param self $other Other.
	 * @return bool
	 */
	public function isHeavierThan( self $other ): bool {
		return $this->kilograms > $other->kilograms;
	}
}

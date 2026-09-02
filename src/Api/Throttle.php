<?php
/**
 * Keeps request rate under ACS's 10-calls-per-second ceiling.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class Throttle {

	private int $maxPerSecond;
	/** @var callable */
	private $sleeper;
	/** @var callable */
	private $clock;
	/** @var list<float> */
	private array $recent = array();

	public function __construct( int $maxPerSecond = 8, ?callable $sleeper = null, ?callable $clock = null ) {
		$this->maxPerSecond = max( 1, $maxPerSecond );
		$this->sleeper      = $sleeper ?? static function ( float $seconds ): void {
			usleep( (int) round( $seconds * 1000000 ) );
		};
		$this->clock        = $clock ?? static function (): float {
			return microtime( true );
		};
	}

	public function acquire(): void {
		$now          = ( $this->clock )();
		$this->recent = array_values(
			array_filter(
				$this->recent,
				static function ( float $t ) use ( $now ): bool {
					return $t > $now - 1.0;
				}
			)
		);

		if ( count( $this->recent ) >= $this->maxPerSecond ) {
			$oldest = $this->recent[0];
			$wait   = 1.0 - ( $now - $oldest );
			if ( $wait > 0 ) {
				( $this->sleeper )( $wait );
			}
		}

		$this->recent[] = ( $this->clock )();
	}
}

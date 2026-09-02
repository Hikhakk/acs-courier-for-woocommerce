<?php
/**
 * Keeps request rate under ACS's 10-calls-per-second ceiling.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * Keeps the request rate under the ACS ceiling of ten calls per second.
 */
final class Throttle {

	/**
	 * Requests allowed per second.
	 *
	 * @var int
	 */
	private int $maxPerSecond;
	/**
	 * Injected collaborator.
	 *
	 * @var callable
	 */
	private $sleeper;
	/**
	 * Injected collaborator.
	 *
	 * @var callable
	 */
	private $clock;
	/**
	 * Timestamps of recent requests.
	 *
	 * @var list<float>
	 */
	private array $recent = array();

	/**
	 * __construct.
	 *
	 * @param int           $maxPerSecond Max per second.
	 * @param callable|null $sleeper Sleeper.
	 * @param callable|null $clock Clock.
	 */
	public function __construct( int $maxPerSecond = 8, ?callable $sleeper = null, ?callable $clock = null ) {
		$this->maxPerSecond = max( 1, $maxPerSecond );
		$this->sleeper      = $sleeper ?? static function ( float $seconds ): void {
			usleep( (int) round( $seconds * 1000000 ) );
		};
		$this->clock        = $clock ?? static function (): float {
			return microtime( true );
		};
	}

	/**
	 * Acquire.
	 */
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

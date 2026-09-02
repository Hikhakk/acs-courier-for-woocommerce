<?php
/**
 * Decorates AcsClient with bounded exponential backoff for transient failures.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * Decorates AcsClient with bounded exponential backoff.
 */
final class RetryingClient {

	/**
	 * The client being decorated.
	 *
	 * @var AcsClient
	 */
	private AcsClient $inner;
	/**
	 * Total attempts before giving up.
	 *
	 * @var int
	 */
	private int $maxAttempts;
	/**
	 * Injected collaborator.
	 *
	 * @var callable
	 */
	private $sleeper;

	/**
	 * __construct.
	 *
	 * @param AcsClient     $inner Inner.
	 * @param int           $maxAttempts Max attempts.
	 * @param callable|null $sleeper Sleeper.
	 */
	public function __construct( AcsClient $inner, int $maxAttempts = 3, ?callable $sleeper = null ) {
		$this->inner       = $inner;
		$this->maxAttempts = max( 1, $maxAttempts );
		$this->sleeper     = $sleeper ?? static function ( float $seconds ): void {
			usleep( (int) round( $seconds * 1000000 ) );
		};
	}

	/**
	 * Calls ACS, retrying transient failures.
	 *
	 * @param string              $alias  ACS method name.
	 * @param array<string,mixed> $params Method parameters.
	 * @return array<string,mixed>
	 * @throws AcsException If the failure is not retryable or attempts are exhausted.
	 */
	public function call( string $alias, array $params = array() ): array {
		$attempt = 0;

		while ( true ) {
			++$attempt;
			try {
				return $this->inner->call( $alias, $params );
			} catch ( AcsException $e ) {
				if ( ! $e->isRetryable() || $attempt >= $this->maxAttempts ) {
					throw $e;
				}
				( $this->sleeper )( $this->backoffSeconds( $attempt ) );
			}
		}
	}

	/**
	 * Backoff seconds.
	 *
	 * @param int $attempt Attempt.
	 * @return float
	 */
	private function backoffSeconds( int $attempt ): float {
		// 0.5s, 1s, 2s ... capped at 8s.
		return min( 8.0, 0.5 * ( 2 ** ( $attempt - 1 ) ) );
	}
}

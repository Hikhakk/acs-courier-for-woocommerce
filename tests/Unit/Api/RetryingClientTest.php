<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use PHPUnit\Framework\TestCase;

final class RetryingClientTest extends TestCase {

	private const OK = '{"ACSExecution_HasError":false,"ACSExecutionErrorMessage":"","ACSOutputResponce":{"ACSTableOutput":{"Table_Data":[]}}}';

	private function inner( array $queue ): AcsClient {
		return new AcsClient( new ArrayTransport( $queue ), new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
	}

	public function test_it_retries_a_rate_limit_and_then_succeeds(): void {
		$slept  = array();
		$client = new RetryingClient(
			$this->inner( array( new TransportResponse( 406, 'Not Acceptable' ), new TransportResponse( 200, self::OK ) ) ),
			3,
			static function ( float $seconds ) use ( &$slept ): void {
				$slept[] = $seconds; }
		);

		$client->call( 'ACS_Stations' );

		self::assertCount( 1, $slept );
		self::assertGreaterThan( 0.0, $slept[0] );
	}

	public function test_it_backs_off_exponentially(): void {
		$slept  = array();
		$client = new RetryingClient(
			$this->inner(
				array(
					new TransportResponse( 503, 'x' ),
					new TransportResponse( 503, 'x' ),
					new TransportResponse( 200, self::OK ),
				)
			),
			3,
			static function ( float $s ) use ( &$slept ): void {
				$slept[] = $s; }
		);

		$client->call( 'ACS_Stations' );

		self::assertCount( 2, $slept );
		self::assertGreaterThan( $slept[0], $slept[1], 'Second backoff must exceed the first.' );
	}

	public function test_it_never_retries_an_auth_failure(): void {
		$slept  = array();
		$client = new RetryingClient(
			$this->inner( array( new TransportResponse( 403, 'Forbidden' ) ) ),
			3,
			static function ( float $s ) use ( &$slept ): void {
				$slept[] = $s; }
		);

		try {
			$client->call( 'ACS_Stations' );
			self::fail( 'Expected AcsException' );
		} catch ( AcsException $e ) {
			self::assertSame( AcsException::KIND_AUTH, $e->kind() );
		}
		self::assertSame( array(), $slept, 'A 403 must not be retried.' );
	}

	public function test_it_never_retries_a_business_error(): void {
		$body   = '{"ACSExecution_HasError":true,"ACSExecutionErrorMessage":"Invalid pick-up date","ACSOutputResponce":{}}';
		$slept  = array();
		$client = new RetryingClient(
			$this->inner( array( new TransportResponse( 200, $body ) ) ),
			3,
			static function ( float $s ) use ( &$slept ): void {
				$slept[] = $s; }
		);

		$this->expectException( AcsException::class );
		try {
			$client->call( 'ACS_Create_Voucher' );
		} finally {
			self::assertSame( array(), $slept );
		}
	}

	public function test_it_gives_up_after_max_attempts(): void {
		$client = new RetryingClient(
			$this->inner(
				array(
					new TransportResponse( 503, 'x' ),
					new TransportResponse( 503, 'x' ),
					new TransportResponse( 503, 'x' ),
				)
			),
			3,
			static function ( float $s ): void {}
		);

		$this->expectException( AcsException::class );
		$client->call( 'ACS_Stations' );
	}
}

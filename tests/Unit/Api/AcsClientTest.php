<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\TransportResponse;
use PHPUnit\Framework\TestCase;

final class AcsClientTest extends TestCase {

	private function fixture( string $name ): string {
		return (string) file_get_contents( __DIR__ . '/../../fixtures/' . $name . '.json' );
	}

	private function client( array $queue, ?ArrayTransport &$transport = null ): AcsClient {
		$transport = new ArrayTransport( $queue );
		return new AcsClient( $transport, new Credentials( 'CO', 'cpw', 'USER', 'upw', 'api-key' ) );
	}

	public function test_it_sends_the_alias_credentials_and_api_key_header(): void {
		$client = $this->client( array( new TransportResponse( 200, $this->fixture( 'station_by_zip_cy' ) ) ), $transport );
		$client->call( 'ACS_Find_Station_By_Zip_Code', array( 'Zip_Code' => '1010' ) );

		$sent = $transport->requests()[0];
		self::assertSame( AcsClient::ENDPOINT, $sent['url'] );
		self::assertSame( 'ACS_Find_Station_By_Zip_Code', $sent['payload']['ACSAlias'] );
		self::assertSame( 'CO', $sent['payload']['ACSInputParameters']['Company_ID'] );
		self::assertSame( '1010', $sent['payload']['ACSInputParameters']['Zip_Code'] );
		self::assertSame( 'api-key', $sent['headers']['AcsApiKey'] );
		self::assertSame( 'application/json', $sent['headers']['Content-Type'] );
	}

	public function test_it_returns_the_misspelled_envelope_contents(): void {
		$client   = $this->client( array( new TransportResponse( 200, $this->fixture( 'station_by_zip_cy' ) ) ) );
		$response = $client->call( 'ACS_Find_Station_By_Zip_Code', array( 'Zip_Code' => '1010' ) );

		self::assertArrayHasKey( 'ACSTableOutput', $response );
		self::assertSame( 'NI', $client->tableData( $response )[0]['ACS Station'] );
	}

	public function test_it_detects_the_silent_business_error(): void {
		$client = $this->client( array( new TransportResponse( 200, $this->fixture( 'tracking_bogus_voucher' ) ) ) );

		$this->expectException( AcsException::class );
		$this->expectExceptionMessage( 'Νο Acs shipment found for your company with voucher no: 0' );

		$client->call( 'ACS_Trackingsummary', array( 'Voucher_No' => '0000000000' ) );
	}

	public function test_a_null_nested_error_is_not_an_error(): void {
		$client = $this->client( array( new TransportResponse( 200, $this->fixture( 'station_by_zip_cy' ) ) ) );
		$this->expectNotToPerformAssertions();
		$client->call( 'ACS_Find_Station_By_Zip_Code', array( 'Zip_Code' => '1010' ) );
	}

	public function test_it_detects_the_top_level_error_channel(): void {
		$body   = json_encode(
			array(
				'ACSExecution_HasError'    => true,
				'ACSExecutionErrorMessage' => 'Invalid pick-up date',
				'ACSOutputResponce'        => array(),
			)
		);
		$client = $this->client( array( new TransportResponse( 200, (string) $body ) ) );

		$this->expectException( AcsException::class );
		$this->expectExceptionMessage( 'Invalid pick-up date' );
		$client->call( 'ACS_Create_Voucher' );
	}

	public function test_403_is_an_auth_error(): void {
		$client = $this->client( array( new TransportResponse( 403, 'Forbidden' ) ) );
		try {
			$client->call( 'ACS_Stations' );
			self::fail( 'Expected AcsException' );
		} catch ( AcsException $e ) {
			self::assertSame( AcsException::KIND_AUTH, $e->kind() );
			self::assertFalse( $e->isRetryable() );
		}
	}

	public function test_406_is_a_retryable_rate_limit(): void {
		$client = $this->client( array( new TransportResponse( 406, 'Not Acceptable' ) ) );
		try {
			$client->call( 'ACS_Stations' );
			self::fail( 'Expected AcsException' );
		} catch ( AcsException $e ) {
			self::assertSame( AcsException::KIND_RATE_LIMITED, $e->kind() );
			self::assertTrue( $e->isRetryable() );
		}
	}

	public function test_malformed_json_is_reported_as_malformed(): void {
		$client = $this->client( array( new TransportResponse( 200, 'not json at all' ) ) );
		try {
			$client->call( 'ACS_Stations' );
			self::fail( 'Expected AcsException' );
		} catch ( AcsException $e ) {
			self::assertSame( AcsException::KIND_MALFORMED, $e->kind() );
		}
	}

	public function test_server_errors_are_retryable_transport_failures(): void {
		$client = $this->client( array( new TransportResponse( 503, 'Service Unavailable' ) ) );
		try {
			$client->call( 'ACS_Stations' );
			self::fail( 'Expected AcsException' );
		} catch ( AcsException $e ) {
			self::assertSame( AcsException::KIND_TRANSPORT, $e->kind() );
			self::assertTrue( $e->isRetryable() );
		}
	}
}

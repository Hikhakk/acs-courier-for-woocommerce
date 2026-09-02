<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\TransportResponse;
use PHPUnit\Framework\TestCase;

final class ArrayTransportTest extends TestCase {

	public function test_it_returns_queued_responses_in_order(): void {
		$transport = new ArrayTransport(
			array(
				new TransportResponse( 200, '{"a":1}' ),
				new TransportResponse( 403, 'denied' ),
			)
		);

		self::assertSame( '{"a":1}', $transport->post( 'https://x', array(), array() )->body );
		self::assertSame( 403, $transport->post( 'https://x', array(), array() )->status );
	}

	public function test_it_records_what_was_sent(): void {
		$transport = new ArrayTransport( array( new TransportResponse( 200, '{}' ) ) );
		$transport->post( 'https://x', array( 'ACSAlias' => 'Ping' ), array( 'AcsApiKey' => 'k' ) );

		$recorded = $transport->requests();
		self::assertCount( 1, $recorded );
		self::assertSame( 'Ping', $recorded[0]['payload']['ACSAlias'] );
		self::assertSame( 'k', $recorded[0]['headers']['AcsApiKey'] );
	}

	public function test_it_throws_when_the_queue_is_exhausted(): void {
		$transport = new ArrayTransport( array() );
		$this->expectException( \RuntimeException::class );
		$transport->post( 'https://x', array(), array() );
	}
}

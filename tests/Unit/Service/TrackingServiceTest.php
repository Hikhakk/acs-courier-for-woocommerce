<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use AcsCourier\Service\TrackingService;
use PHPUnit\Framework\TestCase;

final class TrackingServiceTest extends TestCase {

	private function service( array $queue, ?ArrayTransport &$transport = null ): TrackingService {
		$transport = new ArrayTransport( $queue );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		return new TrackingService( new RetryingClient( $client, 1, static function ( float $s ): void {} ) );
	}

	private function summary( array $row ): string {
		return (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array( array( 'Error_Message' => null ) ),
					'ACSTableOutput' => array( 'Table_Data' => array( $row ) ),
				),
			)
		);
	}

	public function test_it_reads_a_delivered_shipment(): void {
		$service = $this->service(
			array(
				new TransportResponse(
					200,
					$this->summary(
						array(
							'voucher_no'               => '7227889174',
							'shipment_status'          => 4,
							'delivery_flag'            => 1,
							'returned_flag'            => 0,
							'non_delivery_reason_code' => '',
							'delivery_date'            => '2018-12-21T00:00:00',
						)
					)
				),
			)
		);

		$status = $service->summary( '7227889174' );

		self::assertTrue( $status->isDelivered() );
		self::assertSame( 4, $status->code() );
	}

	public function test_it_reads_a_returned_shipment_with_its_reason(): void {
		$service = $this->service(
			array(
				new TransportResponse(
					200,
					$this->summary(
						array(
							'voucher_no'               => '1',
							'shipment_status'          => 7,
							'delivery_flag'            => 1,
							'returned_flag'            => 1,
							'non_delivery_reason_code' => 'LS3',
						)
					)
				),
			)
		);

		$status = $service->summary( '1' );

		self::assertTrue( $status->isReturned() );
		self::assertStringContainsStringIgnoringCase( 'address', $status->nonDeliveryReason() );
	}

	public function test_it_sends_the_tracking_summary_alias(): void {
		$service = $this->service( array( new TransportResponse( 200, $this->summary( array( 'shipment_status' => 2 ) ) ) ), $transport );
		$service->summary( '999' );

		$sent = $transport->requests()[0]['payload'];
		self::assertSame( 'ACS_Trackingsummary', $sent['ACSAlias'] );
		self::assertSame( '999', $sent['ACSInputParameters']['Voucher_No'] );
	}

	public function test_an_unknown_voucher_surfaces_the_acs_message(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array( array( 'Error_Message' => 'Νο Acs shipment found for your company with voucher no: 0' ) ),
					'ACSTableOutput' => array(),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ) );

		$this->expectException( \AcsCourier\Api\AcsException::class );
		$this->expectExceptionMessage( 'Νο Acs shipment found' );
		$service->summary( '0' );
	}

	public function test_an_empty_result_set_is_an_error_not_a_silent_in_transit(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array( array( 'Error_Message' => null ) ),
					'ACSTableOutput' => array( 'Table_Data' => array() ),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ) );

		$this->expectException( \AcsCourier\Api\AcsException::class );
		$service->summary( '1' );
	}

	public function test_checkpoints_are_returned_newest_last(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array( array( 'Error_Message' => null ) ),
					'ACSTableOutput' => array(
						'Table_Data' => array(
							array(
								'checkpoint_date_time' => '2019-01-26T13:05:57',
								'checkpoint_action'    => 'Departure',
								'checkpoint_location'  => 'KAVALA',
								'checkpoint_notes'     => '',
							),
							array(
								'checkpoint_date_time' => '2019-01-28T09:21:00',
								'checkpoint_action'    => 'Delivery to consignee',
								'checkpoint_location'  => 'MAROUSI',
								'checkpoint_notes'     => '',
							),
						),
					),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ), $transport );

		$events = $service->details( '1' );

		self::assertCount( 2, $events );
		self::assertSame( 'Delivery to consignee', $events[1]['action'] );
		self::assertSame( 'MAROUSI', $events[1]['location'] );
		self::assertSame( 'ACS_TrackingDetails', $transport->requests()[0]['payload']['ACSAlias'] );
	}
}

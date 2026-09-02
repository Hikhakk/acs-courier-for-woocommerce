<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use AcsCourier\Service\PickupListService;
use AcsCourier\Service\UnprintedVouchersException;
use PHPUnit\Framework\TestCase;

final class PickupListServiceTest extends TestCase {

	private function service( array $queue, ?ArrayTransport &$transport = null ): PickupListService {
		$transport = new ArrayTransport( $queue );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		return new PickupListService( new RetryingClient( $client, 1, static function ( float $s ): void {} ) );
	}

	public function test_it_returns_the_pickup_list_number(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'PickupList_No'   => '7227889830',
							'Unprinted_Found' => 0,
							'Error_Message'   => '',
						),
					),
					'ACSTableOutput' => array( 'Table_Data' => array() ),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ) );

		self::assertSame( '7227889830', $service->issue( '2026-09-03' ) );
	}

	/**
	 * ACS reports unprinted vouchers as a successful call with a count, not as
	 * an error. Treating that as success would leave barcodes unrecognised.
	 */
	public function test_unprinted_vouchers_block_the_list_and_are_named(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'PickupList_No'   => null,
							'Unprinted_Found' => 2,
							'Error_Message'   => 'Αδύνατη η έκδοση λίστας παραλαβής.',
						),
					),
					'ACSTableOutput' => array(
						'Table_Data' => array(
							array( 'Unprinted_Vouchers' => '7227889841' ),
							array( 'Unprinted_Vouchers' => '7227889874' ),
						),
					),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ) );

		try {
			$service->issue( '2026-09-03' );
			self::fail( 'Expected UnprintedVouchersException' );
		} catch ( UnprintedVouchersException $e ) {
			self::assertSame( array( '7227889841', '7227889874' ), $e->vouchers() );
			self::assertStringContainsString( '2', $e->getMessage() );
		}
	}

	public function test_it_sends_the_issue_alias_with_the_pickup_date(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'PickupList_No'   => '1',
							'Unprinted_Found' => 0,
							'Error_Message'   => '',
						),
					),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ), $transport );
		$service->issue( '2026-09-03' );

		$sent = $transport->requests()[0]['payload'];
		self::assertSame( 'ACS_Issue_Pickup_List', $sent['ACSAlias'] );
		self::assertSame( '2026-09-03', $sent['ACSInputParameters']['Pickup_Date'] );
	}

	public function test_a_missing_list_number_without_unprinted_vouchers_is_an_error(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'PickupList_No'   => null,
							'Unprinted_Found' => 0,
							'Error_Message'   => '',
						),
					),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ) );

		$this->expectException( \AcsCourier\Api\AcsException::class );
		$service->issue( '2026-09-03' );
	}

	public function test_printing_a_list_returns_pdf_bytes(): void {
		$body    = (string) json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSObjectOutput' => array( array( '7227889830' => base64_encode( '%PDF list' ) ) ),
					'ACSValueOutput'  => array( array( 'Error_Message' => null ) ),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, $body ) ), $transport );

		self::assertSame( '%PDF list', $service->printList( '7227889830', '2026-09-03' ) );
		self::assertSame( 'ACS_Print_Pickup_List', $transport->requests()[0]['payload']['ACSAlias'] );
	}
}

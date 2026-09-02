<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\ArrayTransport;
use AcsCourier\Api\Credentials;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\TransportResponse;
use AcsCourier\Domain\Country;
use AcsCourier\Domain\Shipment;
use AcsCourier\Domain\Weight;
use AcsCourier\Service\ShipmentService;
use PHPUnit\Framework\TestCase;

final class ShipmentServiceTest extends TestCase {

	private function validShipment(): Shipment {
		$s                         = new Shipment();
		$s->recipientName          = 'TEST RECIPIENT';
		$s->recipientAddress       = 'ΑΣΚΛΗΠΙΟΥ';
		$s->recipientAddressNumber = '25';
		$s->recipientZip           = '1010';
		$s->recipientRegion        = 'ΛΕΥΚΩΣΙΑ';
		$s->recipientCellPhone     = '99000000';
		$s->country                = Country::cyprus();
		$s->weight                 = Weight::fromKilograms( 1.0 );
		$s->pickupDate             = '2026-09-03';
		$s->sender                 = 'ESHOP';
		$s->billingCode            = '2XX000000';
		$s->contentTypeId          = 6;
		return $s;
	}

	private function service( array $queue, ?ArrayTransport &$transport = null ): ShipmentService {
		$transport = new ArrayTransport( $queue );
		$client    = new AcsClient( $transport, new Credentials( 'C', 'p', 'U', 'p', 'k' ) );
		return new ShipmentService( new RetryingClient( $client, 1, static function ( float $s ): void {} ) );
	}

	public function test_it_returns_the_voucher_number(): void {
		$body    = json_encode(
			array(
				'ACSExecution_HasError'    => false,
				'ACSExecutionErrorMessage' => '',
				'ACSOutputResponce'        => array(
					'ACSValueOutput' => array(
						array(
							'Voucher_No'        => '7227889174',
							'Voucher_No_Return' => null,
							'Error_Message'     => '',
						),
					),
					'ACSTableOutput' => array(),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, (string) $body ) ) );

		self::assertSame( '7227889174', $service->create( $this->validShipment() ) );
	}

	public function test_it_refuses_to_call_acs_with_invalid_data(): void {
		$service                 = $this->service( array(), $transport );
		$shipment                = $this->validShipment();
		$shipment->contentTypeId = null;

		try {
			$service->create( $shipment );
			self::fail( 'Expected InvalidArgumentException' );
		} catch ( \InvalidArgumentException $e ) {
			self::assertStringContainsString( 'content type', strtolower( $e->getMessage() ) );
		}

		self::assertSame( array(), $transport->requests(), 'No API call may be made for invalid data.' );
	}

	public function test_it_sends_the_create_voucher_alias(): void {
		$body    = json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'Voucher_No'    => '1',
							'Error_Message' => '',
						),
					),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, (string) $body ) ), $transport );
		$service->create( $this->validShipment() );

		self::assertSame( 'ACS_Create_Voucher', $transport->requests()[0]['payload']['ACSAlias'] );
	}

	public function test_a_business_error_surfaces_as_an_exception(): void {
		$body    = json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array(
					'ACSValueOutput' => array(
						array(
							'Voucher_No'    => null,
							'Error_Message' => 'Invalid pick-up date',
						),
					),
				),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, (string) $body ) ) );

		$this->expectExceptionMessage( 'Invalid pick-up date' );
		$service->create( $this->validShipment() );
	}

	public function test_a_missing_voucher_number_is_an_error_not_a_silent_success(): void {
		$body    = json_encode(
			array(
				'ACSExecution_HasError' => false,
				'ACSOutputResponce'     => array( 'ACSValueOutput' => array( array( 'Error_Message' => '' ) ) ),
			)
		);
		$service = $this->service( array( new TransportResponse( 200, (string) $body ) ) );

		$this->expectException( \AcsCourier\Api\AcsException::class );
		$service->create( $this->validShipment() );
	}
}

<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Mapping;

use AcsCourier\Domain\Country;
use AcsCourier\Domain\Shipment;
use AcsCourier\Domain\Weight;
use AcsCourier\Mapping\FieldMap;
use PHPUnit\Framework\TestCase;

final class FieldMapTest extends TestCase {

	private function cyprusShipment( array $overrides = array() ): Shipment {
		$s                         = new Shipment();
		$s->recipientName          = 'TEST RECIPIENT';
		$s->recipientAddress       = 'ΑΣΚΛΗΠΙΟΥ';
		$s->recipientAddressNumber = '25';
		$s->recipientZip           = '1010';
		$s->recipientRegion        = 'ΛΕΥΚΩΣΙΑ';
		$s->recipientPhone         = '22000000';
		$s->recipientCellPhone     = '99000000';
		$s->recipientEmail         = 'buyer@example.com';
		$s->country                = Country::cyprus();
		$s->weight                 = Weight::fromKilograms( 1.2 );
		$s->itemQuantity           = 1;
		$s->pickupDate             = '2026-09-03';
		$s->sender                 = 'ESHOP';
		$s->billingCode            = '2XX000000';
		$s->chargeType             = 2;
		$s->contentTypeId          = 6;
		$s->language               = 'EN';

		foreach ( $overrides as $k => $v ) {
			$s->$k = $v;
		}
		return $s;
	}

	public function test_it_emits_the_exact_acs_field_names_including_misspellings(): void {
		$params = FieldMap::toCreateVoucherParams( $this->cyprusShipment() );

		self::assertSame( 'TEST RECIPIENT', $params['Recipient_Name'] );
		self::assertSame( 'ΑΣΚΛΗΠΙΟΥ', $params['Recipient_Address'] );
		self::assertSame( '25', $params['Recipient_Address_Number'] );
		self::assertSame( '1010', $params['Recipient_Zipcode'] );
		self::assertSame( 'CY', $params['Recipient_Country'] );
		self::assertSame( 1.2, $params['Weight'] );
		self::assertSame( 2, $params['Charge_Type'] );

		self::assertArrayHasKey( 'Cod_Ammount', $params );
		self::assertArrayHasKey( 'Insurance_Ammount', $params );
		self::assertNull( $params['Cod_Ammount'], 'Prepaid: COD must be null.' );
	}

	public function test_weight_is_clamped_to_the_acs_floor(): void {
		$params = FieldMap::toCreateVoucherParams(
			$this->cyprusShipment( array( 'weight' => Weight::fromKilograms( 0.1 ) ) )
		);
		self::assertSame( 0.5, $params['Weight'] );
	}

	public function test_cyprus_requires_a_content_type(): void {
		$problems = FieldMap::validate( $this->cyprusShipment( array( 'contentTypeId' => null ) ) );
		self::assertNotEmpty( $problems );
		self::assertStringContainsString( 'content type', strtolower( implode( ' ', $problems ) ) );
	}

	public function test_greece_does_not_require_a_content_type(): void {
		$s = $this->cyprusShipment(
			array(
				'country'       => Country::greece(),
				'recipientZip'  => '17778',
				'contentTypeId' => null,
			)
		);
		self::assertSame( array(), FieldMap::validate( $s ) );
	}

	public function test_a_zip_of_the_wrong_length_is_rejected(): void {
		$problems = FieldMap::validate( $this->cyprusShipment( array( 'recipientZip' => '17778' ) ) );
		self::assertNotEmpty( $problems );
		self::assertStringContainsString( 'postcode', strtolower( implode( ' ', $problems ) ) );
	}

	public function test_a_missing_recipient_name_is_rejected(): void {
		self::assertNotEmpty( FieldMap::validate( $this->cyprusShipment( array( 'recipientName' => '  ' ) ) ) );
	}

	public function test_locker_delivery_rejects_multi_piece_shipments(): void {
		$problems = FieldMap::validate(
			$this->cyprusShipment(
				array(
					'stationDestination'       => 'NI',
					'stationBranchDestination' => 503,
					'itemQuantity'             => 2,
				)
			)
		);
		self::assertNotEmpty( $problems );
		self::assertStringContainsString( 'pickup point', strtolower( implode( ' ', $problems ) ) );
	}

	public function test_locker_fields_are_emitted_when_a_point_is_chosen(): void {
		$params = FieldMap::toCreateVoucherParams(
			$this->cyprusShipment(
				array(
					'stationDestination'       => 'NI',
					'stationBranchDestination' => 503,
					'deliveryProducts'         => array( 'REC' ),
				)
			)
		);

		self::assertSame( 'NI', $params['Acs_Station_Destination'] );
		self::assertSame( 503, $params['Acs_Station_Branch_Destination'] );
		self::assertSame( 'REC', $params['Acs_Delivery_Products'] );
	}

	public function test_multiple_delivery_products_are_comma_joined(): void {
		$params = FieldMap::toCreateVoucherParams(
			$this->cyprusShipment( array( 'deliveryProducts' => array( 'REC', 'SAT' ) ) )
		);
		self::assertSame( 'REC,SAT', $params['Acs_Delivery_Products'] );
	}

	public function test_cod_to_a_pickup_point_requires_an_email(): void {
		$problems = FieldMap::validate(
			$this->cyprusShipment(
				array(
					'stationDestination'       => 'NI',
					'stationBranchDestination' => 503,
					'codAmount'                => 50.0,
					'recipientEmail'           => '',
				)
			)
		);

		self::assertNotEmpty( $problems );
		self::assertStringContainsString( 'email', strtolower( implode( ' ', $problems ) ) );
	}

	public function test_a_cod_amount_reaches_the_misspelled_acs_field(): void {
		$params = FieldMap::toCreateVoucherParams(
			$this->cyprusShipment(
				array(
					'codAmount'     => 50.5,
					'codPaymentWay' => 0,
				)
			)
		);

		self::assertSame( 50.5, $params['Cod_Ammount'] );
		self::assertSame( 0, $params['Cod_Payment_Way'] );
	}

	public function test_more_than_99_pieces_is_rejected(): void {
		self::assertNotEmpty( FieldMap::validate( $this->cyprusShipment( array( 'itemQuantity' => 100 ) ) ) );
	}
}

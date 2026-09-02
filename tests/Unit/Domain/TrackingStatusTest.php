<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Domain;

use AcsCourier\Domain\TrackingStatus;
use PHPUnit\Framework\TestCase;

final class TrackingStatusTest extends TestCase {

	public function test_status_four_is_delivered(): void {
		$s = new TrackingStatus( 4, 1, 0, '' );
		self::assertTrue( $s->isDelivered() );
		self::assertFalse( $s->isReturning() );
		self::assertFalse( $s->isReturned() );
	}

	public function test_status_six_is_on_its_way_back(): void {
		$s = new TrackingStatus( 6, 0, 0, '' );
		self::assertTrue( $s->isReturning() );
		self::assertFalse( $s->isDelivered() );
	}

	public function test_status_seven_with_returned_flag_is_back_with_the_sender(): void {
		$s = new TrackingStatus( 7, 1, 1, '' );
		self::assertTrue( $s->isReturned() );
	}

	/**
	 * ACS sets delivery_flag to 1 when a returned parcel reaches the sender, so
	 * that flag alone must never be read as "delivered to the recipient".
	 */
	public function test_a_parcel_delivered_back_to_the_sender_is_not_delivered(): void {
		$s = new TrackingStatus( 7, 1, 1, '' );

		self::assertFalse( $s->isDelivered(), 'Returned to sender is not delivered.' );
		self::assertTrue( $s->isReturned() );
	}

	public function test_the_delivery_flag_confirms_delivery_when_nothing_was_returned(): void {
		self::assertTrue( ( new TrackingStatus( 4, 1, 0, '' ) )->isDelivered() );
	}

	public function test_status_four_without_the_delivery_flag_is_not_yet_delivered(): void {
		self::assertFalse(
			( new TrackingStatus( 4, 0, 0, '' ) )->isDelivered(),
			'ACS has not confirmed handover.'
		);
	}

	public function test_it_is_in_transit_when_it_is_none_of_those(): void {
		self::assertTrue( ( new TrackingStatus( 2, 0, 0, '' ) )->isInTransit() );
		self::assertFalse( ( new TrackingStatus( 4, 1, 0, '' ) )->isInTransit() );
	}

	/** @dataProvider nonDeliveryProvider */
	public function test_non_delivery_codes_read_as_english( string $code, string $expected ): void {
		$s = new TrackingStatus( 5, 0, 0, $code );
		self::assertStringContainsStringIgnoringCase( $expected, $s->nonDeliveryReason() );
	}

	public static function nonDeliveryProvider(): array {
		return array(
			array( 'AD1', 'office pickup' ),
			array( 'AP1', 'refus' ),
			array( 'AS1', 'Absent' ),
			array( 'LS3', 'address' ),
			array( 'PA4', 'edirect' ),
		);
	}

	public function test_an_unknown_code_is_reported_verbatim_not_swallowed(): void {
		$s = new TrackingStatus( 5, 0, 0, 'ZZ9' );
		self::assertStringContainsString( 'ZZ9', $s->nonDeliveryReason() );
	}

	public function test_no_code_means_no_reason(): void {
		self::assertSame( '', ( new TrackingStatus( 2, 0, 0, '' ) )->nonDeliveryReason() );
	}
}

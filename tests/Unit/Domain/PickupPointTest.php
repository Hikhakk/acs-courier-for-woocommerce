<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Domain;

use AcsCourier\Domain\PickupPoint;
use PHPUnit\Framework\TestCase;

final class PickupPointTest extends TestCase {

	private function point( array $o = array() ): PickupPoint {
		return PickupPoint::fromAcsRow(
			array_merge(
				array(
					'ACS_SHOP_STATION_ID'    => 'NI',
					'ACS_SHOP_BRANCH_ID'     => 503,
					'ACS_SHOP_STATION_DESCR' => 'ACS LOCKER ALPHAMEGA EGKOMIS',
					'ACS_SHOP_ADDRESS'       => 'EGKOMI',
					'ACS_SHOP_ZIPCODE'       => '2412',
					'ACS_SHOP_LAT'           => '35.161',
					'ACS_SHOP_LONG'          => '33.321',
					'ACS_SHOP_COUNTRY_ID'    => 'CY',
					'ACS_SHOP_KIND'          => 8,
					'ACS_SHOP_WORKING_HOURS' => '08:00-20:00',
				),
				$o
			)
		);
	}

	public function test_it_maps_an_acs_row(): void {
		$p = $this->point();

		self::assertSame( 'NI', $p->stationId() );
		self::assertSame( 503, $p->branchId() );
		self::assertSame( 'ACS LOCKER ALPHAMEGA EGKOMIS', $p->name() );
		self::assertSame( '2412', $p->postcode() );
		self::assertSame( 'CY', $p->country() );
	}

	public function test_kind_eight_is_a_locker(): void {
		self::assertTrue( $this->point( array( 'ACS_SHOP_KIND' => 8 ) )->isLocker() );
		self::assertFalse( $this->point( array( 'ACS_SHOP_KIND' => 1 ) )->isLocker() );
	}

	public function test_it_has_a_stable_identifier(): void {
		self::assertSame( 'NI:503', $this->point()->id() );
	}

	public function test_distance_uses_the_haversine_formula(): void {
		// Nicosia to Limassol is roughly 65 km.
		$nicosia  = $this->point(
			array(
				'ACS_SHOP_LAT'  => '35.1856',
				'ACS_SHOP_LONG' => '33.3823',
			)
		);
		$limassol = array(
			'lat' => 34.7071,
			'lng' => 33.0226,
		);

		$km = $nicosia->distanceKm( $limassol['lat'], $limassol['lng'] );

		self::assertGreaterThan( 50.0, $km );
		self::assertLessThan( 80.0, $km );
	}

	public function test_distance_is_null_without_coordinates(): void {
		$p = $this->point(
			array(
				'ACS_SHOP_LAT'  => '',
				'ACS_SHOP_LONG' => '',
			)
		);
		self::assertNull( $p->distanceKm( 35.0, 33.0 ) );
	}

	public function test_it_exposes_its_coordinates(): void {
		$p = $this->point();

		self::assertTrue( $p->hasCoordinates() );
		self::assertSame( 35.161, $p->lat() );
		self::assertSame( 33.321, $p->lng() );
	}

	public function test_a_point_without_coordinates_reports_so(): void {
		$p = $this->point(
			array(
				'ACS_SHOP_LAT'  => '',
				'ACS_SHOP_LONG' => '',
			)
		);

		self::assertFalse( $p->hasCoordinates() );
		self::assertNull( $p->lat() );
		self::assertNull( $p->lng() );
	}

	public function test_a_row_without_a_station_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		PickupPoint::fromAcsRow( array( 'ACS_SHOP_BRANCH_ID' => 1 ) );
	}
}

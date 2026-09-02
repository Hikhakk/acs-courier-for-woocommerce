<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Service;

use AcsCourier\Domain\Weight;
use AcsCourier\Service\RateTable;
use PHPUnit\Framework\TestCase;

final class RateTableTest extends TestCase {

	private function table(): RateTable {
		return new RateTable(
			array(
				array(
					'max_kg' => 2.0,
					'home'   => 3.50,
					'locker' => 2.50,
				),
				array(
					'max_kg' => 5.0,
					'home'   => 5.00,
					'locker' => 3.80,
				),
				array(
					'max_kg' => 10.0,
					'home'   => 8.00,
					'locker' => 6.00,
				),
			),
			1.20,   // per extra kg above the top band
			0.0     // free shipping threshold, 0 = disabled
		);
	}

	/** @dataProvider bandProvider */
	public function test_it_charges_the_right_band( float $kg, bool $locker, float $expected ): void {
		self::assertSame( $expected, $this->table()->rate( Weight::fromKilograms( $kg ), $locker ) );
	}

	public static function bandProvider(): array {
		return array(
			'just above zero, home'  => array( 0.1, false, 3.50 ),
			'exactly on a boundary'  => array( 2.0, false, 3.50 ),
			'a gram over a boundary' => array( 2.001, false, 5.00 ),
			'top of second band'     => array( 5.0, false, 5.00 ),
			'third band'             => array( 7.5, false, 8.00 ),
			'locker is cheaper'      => array( 1.0, true, 2.50 ),
			'locker third band'      => array( 7.5, true, 6.00 ),
		);
	}

	public function test_above_the_top_band_it_adds_a_per_kilo_increment(): void {
		// 10 kg costs 8.00; 13 kg adds 3 x 1.20.
		self::assertSame( 11.60, $this->table()->rate( Weight::fromKilograms( 13.0 ), false ) );
	}

	public function test_a_partial_kilo_above_the_top_band_rounds_up(): void {
		// 10.2 kg is charged as one extra kilo.
		self::assertSame( 9.20, $this->table()->rate( Weight::fromKilograms( 10.2 ), false ) );
	}

	public function test_the_free_shipping_threshold_applies_to_the_order_total(): void {
		$table = new RateTable(
			array(
				array(
					'max_kg' => 2.0,
					'home'   => 3.50,
					'locker' => 2.50,
				),
			),
			1.20,
			50.0
		);

		self::assertSame( 0.0, $table->rate( Weight::fromKilograms( 1.0 ), false, 50.0 ) );
		self::assertSame( 0.0, $table->rate( Weight::fromKilograms( 1.0 ), false, 75.0 ) );
		self::assertSame( 3.50, $table->rate( Weight::fromKilograms( 1.0 ), false, 49.99 ) );
	}

	public function test_a_zero_threshold_never_gives_free_shipping(): void {
		self::assertSame( 3.50, $this->table()->rate( Weight::fromKilograms( 1.0 ), false, 10000.0 ) );
	}

	public function test_an_empty_table_yields_no_rate(): void {
		self::assertNull( ( new RateTable( array(), 0.0, 0.0 ) )->rate( Weight::fromKilograms( 1.0 ), false ) );
	}
}

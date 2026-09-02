<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Domain;

use AcsCourier\Domain\Weight;
use PHPUnit\Framework\TestCase;

final class WeightTest extends TestCase {

	public function test_it_clamps_to_the_half_kilo_floor(): void {
		self::assertSame( 0.5, Weight::fromKilograms( 0.0 )->forAcs() );
		self::assertSame( 0.5, Weight::fromKilograms( 0.2 )->forAcs() );
		self::assertSame( 0.75, Weight::fromKilograms( 0.75 )->forAcs() );
	}

	public function test_it_flags_weights_above_the_maximum(): void {
		self::assertTrue( Weight::fromKilograms( 1000.0 )->isAboveMaximum() );
		self::assertFalse( Weight::fromKilograms( 999.0 )->isAboveMaximum() );
		self::assertSame( 999.0, Weight::fromKilograms( 1500.0 )->forAcs() );
	}

	public function test_volumetric_uses_the_5000_divisor(): void {
		self::assertSame( 12.0, Weight::volumetric( 50, 40, 30 )->kilograms() );
	}

	public function test_it_rounds_to_two_decimals(): void {
		self::assertSame( 1.23, Weight::fromKilograms( 1.2345 )->forAcs() );
	}
}

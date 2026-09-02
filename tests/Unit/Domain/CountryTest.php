<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Domain;

use AcsCourier\Domain\Country;
use PHPUnit\Framework\TestCase;

final class CountryTest extends TestCase {

	public function test_cyprus_uses_four_digit_zips_and_greece_five(): void {
		self::assertSame( 4, Country::cyprus()->zipLength() );
		self::assertSame( 5, Country::greece()->zipLength() );

		self::assertTrue( Country::cyprus()->isValidZip( '1010' ) );
		self::assertFalse( Country::cyprus()->isValidZip( '17778' ) );
		self::assertTrue( Country::greece()->isValidZip( '17778' ) );
		self::assertFalse( Country::greece()->isValidZip( '1010' ) );
	}

	public function test_only_cyprus_requires_a_content_type(): void {
		self::assertTrue( Country::cyprus()->requiresContentType() );
		self::assertFalse( Country::greece()->requiresContentType() );
	}

	public function test_only_greece_supports_live_pricing(): void {
		self::assertTrue( Country::greece()->supportsLivePricing() );
		self::assertFalse( Country::cyprus()->supportsLivePricing() );
	}

	public function test_unsupported_countries_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );
		Country::fromCode( 'DE' );
	}

	public function test_codes_are_normalised(): void {
		self::assertSame( 'CY', Country::fromCode( 'cy' )->code() );
		self::assertSame( 'GR', Country::fromCode( ' gr ' )->code() );
	}
}

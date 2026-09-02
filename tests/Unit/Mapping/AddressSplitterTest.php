<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Mapping;

use AcsCourier\Mapping\AddressSplitter;
use PHPUnit\Framework\TestCase;

final class AddressSplitterTest extends TestCase {

	/** @dataProvider addressProvider */
	public function test_it_splits_street_from_number( string $input, string $street, string $number ): void {
		$result = AddressSplitter::split( $input );

		self::assertSame( $street, $result['street'] );
		self::assertSame( $number, $result['number'] );
	}

	public static function addressProvider(): array {
		return array(
			'trailing number'       => array( 'ΑΣΚΛΗΠΙΟΥ 25', 'ΑΣΚΛΗΠΙΟΥ', '25' ),
			'latin trailing number' => array( 'P. RALLI 45', 'P. RALLI', '45' ),
			'number with letter'    => array( 'ΚΗΦΙΣΙΑΣ 12Α', 'ΚΗΦΙΣΙΑΣ', '12Α' ),
			'no number'             => array( 'ΑΓΙΟΥ ΔΟΜΕΤΙΟΥ', 'ΑΓΙΟΥ ΔΟΜΕΤΙΟΥ', '' ),
			'extra whitespace'      => array( '  ΕΡΜΟΥ   10  ', 'ΕΡΜΟΥ', '10' ),
			'hyphenated number'     => array( 'ΣΤΑΔΙΟΥ 5-7', 'ΣΤΑΔΙΟΥ', '5-7' ),
			'empty'                 => array( '', '', '' ),
		);
	}
}

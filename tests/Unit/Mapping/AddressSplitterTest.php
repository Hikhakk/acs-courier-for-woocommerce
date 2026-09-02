<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Mapping;

use AcsCourier\Mapping\AddressSplitter;
use PHPUnit\Framework\TestCase;

final class AddressSplitterTest extends TestCase
{
    /** @dataProvider addressProvider */
    public function test_it_splits_street_from_number(string $input, string $street, string $number): void
    {
        $result = AddressSplitter::split($input);

        self::assertSame($street, $result['street']);
        self::assertSame($number, $result['number']);
    }

    public static function addressProvider(): array
    {
        return [
            'trailing number'       => ['ΑΣΚΛΗΠΙΟΥ 25', 'ΑΣΚΛΗΠΙΟΥ', '25'],
            'latin trailing number' => ['P. RALLI 45', 'P. RALLI', '45'],
            'number with letter'    => ['ΚΗΦΙΣΙΑΣ 12Α', 'ΚΗΦΙΣΙΑΣ', '12Α'],
            'no number'             => ['ΑΓΙΟΥ ΔΟΜΕΤΙΟΥ', 'ΑΓΙΟΥ ΔΟΜΕΤΙΟΥ', ''],
            'extra whitespace'      => ['  ΕΡΜΟΥ   10  ', 'ΕΡΜΟΥ', '10'],
            'hyphenated number'     => ['ΣΤΑΔΙΟΥ 5-7', 'ΣΤΑΔΙΟΥ', '5-7'],
            'empty'                 => ['', '', ''],
        ];
    }
}

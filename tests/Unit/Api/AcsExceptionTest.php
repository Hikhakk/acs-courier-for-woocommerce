<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Api;

use AcsCourier\Api\AcsException;
use PHPUnit\Framework\TestCase;

final class AcsExceptionTest extends TestCase {

	public function test_business_errors_carry_the_acs_message_verbatim(): void {
		$e = AcsException::business( 'Invalid pick-up date', 'ACS_Create_Voucher' );

		self::assertSame( 'Invalid pick-up date', $e->getMessage() );
		self::assertSame( 'ACS_Create_Voucher', $e->alias() );
		self::assertSame( 'business', $e->kind() );
		self::assertFalse( $e->isRetryable() );
	}

	public function test_auth_failures_are_never_retryable(): void {
		self::assertFalse( AcsException::auth( 'X' )->isRetryable() );
		self::assertSame( 'auth', AcsException::auth( 'X' )->kind() );
	}

	/** @dataProvider retryableProvider */
	public function test_only_transient_kinds_are_retryable( AcsException $e, bool $expected ): void {
		self::assertSame( $expected, $e->isRetryable() );
	}

	public static function retryableProvider(): array {
		return array(
			'rate limited' => array( AcsException::rateLimited( 'X' ), true ),
			'transport'    => array( AcsException::transport( 'timeout', 'X' ), true ),
			'malformed'    => array( AcsException::malformed( 'X' ), false ),
			'business'     => array( AcsException::business( 'bad', 'X' ), false ),
		);
	}
}

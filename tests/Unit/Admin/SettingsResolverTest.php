<?php
declare(strict_types=1);

namespace AcsCourier\Tests\Unit\Admin;

use AcsCourier\Admin\SettingsResolver;
use PHPUnit\Framework\TestCase;

final class SettingsResolverTest extends TestCase {

	public function test_stored_values_are_used_when_no_constants_are_defined(): void {
		$c = SettingsResolver::resolve(
			array(
				'company_id'       => 'CO',
				'company_password' => 'cpw',
				'user_id'          => 'U',
				'user_password'    => 'upw',
				'api_key'          => 'k',
			),
			array()
		);
		self::assertSame( 'CO', $c->toArray()['Company_ID'] );
		self::assertSame( 'k', $c->apiKey() );
	}

	public function test_constants_override_stored_values(): void {
		$c = SettingsResolver::resolve(
			array(
				'company_id'       => 'STORED',
				'company_password' => 'cpw',
				'user_id'          => 'U',
				'user_password'    => 'upw',
				'api_key'          => 'stored-key',
			),
			array(
				'ACS_WC_COMPANY_ID' => 'FROM_CONFIG',
				'ACS_WC_API_KEY'    => 'config-key',
			)
		);
		self::assertSame( 'FROM_CONFIG', $c->toArray()['Company_ID'] );
		self::assertSame( 'config-key', $c->apiKey() );
		self::assertSame( 'cpw', $c->toArray()['Company_Password'], 'Unset constants fall through to stored.' );
	}

	public function test_missing_values_yield_incomplete_credentials(): void {
		self::assertFalse( SettingsResolver::resolve( array(), array() )->isComplete() );
	}

	public function test_an_empty_constant_does_not_shadow_a_stored_value(): void {
		$c = SettingsResolver::resolve(
			array(
				'company_id'       => 'STORED',
				'company_password' => 'c',
				'user_id'          => 'u',
				'user_password'    => 'p',
				'api_key'          => 'k',
			),
			array( 'ACS_WC_COMPANY_ID' => '' )
		);
		self::assertSame( 'STORED', $c->toArray()['Company_ID'] );
	}
}

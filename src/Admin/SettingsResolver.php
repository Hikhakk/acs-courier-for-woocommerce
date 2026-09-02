<?php
/**
 * Resolves ACS credentials from settings and wp-config constants.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Admin;

use AcsCourier\Api\Credentials;

/**
 * Resolves ACS credentials, letting wp-config.php constants win over stored
 * options so production secrets need never live in the database.
 */
final class SettingsResolver {

	/**
	 * Maps a settings key to the constant that overrides it.
	 */
	private const MAP = array(
		'company_id'       => 'ACS_WC_COMPANY_ID',
		'company_password' => 'ACS_WC_COMPANY_PASSWORD',
		'user_id'          => 'ACS_WC_USER_ID',
		'user_password'    => 'ACS_WC_USER_PASSWORD',
		'api_key'          => 'ACS_WC_API_KEY',
	);

	/**
	 * Builds credentials, preferring constants over stored settings.
	 *
	 * @param array<string,string> $stored    Stored settings.
	 * @param array<string,string> $constants Defined constants, keyed by name.
	 * @return Credentials
	 */
	public static function resolve( array $stored, array $constants ): Credentials {
		$value = static function ( string $key ) use ( $stored, $constants ): string {
			$constant = self::MAP[ $key ];
			if ( isset( $constants[ $constant ] ) && '' !== $constants[ $constant ] ) {
				return (string) $constants[ $constant ];
			}
			return isset( $stored[ $key ] ) ? (string) $stored[ $key ] : '';
		};

		return new Credentials(
			$value( 'company_id' ),
			$value( 'company_password' ),
			$value( 'user_id' ),
			$value( 'user_password' ),
			$value( 'api_key' )
		);
	}

	/**
	 * Reads the overriding constants that are actually defined.
	 *
	 * @return array<string,string>
	 */
	public static function definedConstants(): array {
		$found = array();
		foreach ( self::MAP as $constant ) {
			if ( defined( $constant ) ) {
				$found[ $constant ] = (string) constant( $constant );
			}
		}
		return $found;
	}

	/**
	 * Whether a settings key is locked by a constant.
	 *
	 * @param string $key Settings key.
	 * @return bool
	 */
	public static function isLockedByConstant( string $key ): bool {
		return isset( self::MAP[ $key ] ) && defined( self::MAP[ $key ] );
	}

	/**
	 * Returns the constant name that backs a settings key.
	 *
	 * @param string $key Settings key.
	 * @return string
	 */
	public static function constantFor( string $key ): string {
		return self::MAP[ $key ] ?? '';
	}
}

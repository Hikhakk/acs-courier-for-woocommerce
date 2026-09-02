<?php
/**
 * ACS settings screen under WooCommerce > Settings > Shipping.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Admin;

/**
 * Registers and persists the plugin's settings.
 */
final class Settings {

	/**
	 * Option name holding every setting.
	 */
	public const OPTION = 'acs_wc_settings';

	/**
	 * Settings section id.
	 */
	public const SECTION = 'acs_courier';

	/**
	 * Keys that hold secrets and must never be echoed back.
	 */
	private const SECRET_KEYS = array( 'company_password', 'user_password', 'api_key' );

	/**
	 * Keys that are safe to render as plain text.
	 */
	private const PLAIN_KEYS = array( 'company_id', 'user_id', 'billing_code', 'sender_name', 'charge_type', 'content_type_id' );

	/**
	 * Hooks the settings screen into WooCommerce.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'woocommerce_get_sections_shipping',
			static function ( $sections ) {
				$sections[ self::SECTION ] = __( 'ACS Courier', 'acs-courier-for-woocommerce' );
				return $sections;
			}
		);

		add_filter( 'woocommerce_get_settings_shipping', array( self::class, 'fields' ), 10, 2 );
		add_action( 'woocommerce_update_options_shipping_' . self::SECTION, array( self::class, 'save' ) );
	}

	/**
	 * Supplies the field definitions for the ACS section.
	 *
	 * @param array<int,array<string,mixed>> $settings Existing settings.
	 * @param string                         $section  Current section.
	 * @return array<int,array<string,mixed>>
	 */
	public static function fields( $settings, $section ) {
		if ( self::SECTION !== $section ) {
			return $settings;
		}

		$stored = self::all();

		$credential = static function ( string $key, string $label, string $type ) use ( $stored ): array {
			$locked   = SettingsResolver::isLockedByConstant( $key );
			$constant = SettingsResolver::constantFor( $key );
			$secret   = in_array( $key, self::SECRET_KEYS, true );

			return array(
				'id'                => 'acs_wc_' . $key,
				'title'             => $label,
				'type'              => $type,
				// Secrets are never rendered back into HTML.
				'value'             => ( $locked || $secret ) ? '' : ( $stored[ $key ] ?? '' ),
				'custom_attributes' => $locked ? array( 'disabled' => 'disabled' ) : array(),
				'desc'              => $locked
					/* translators: %s: PHP constant name. */
					? sprintf( __( 'Set by the %s constant in wp-config.php.', 'acs-courier-for-woocommerce' ), $constant )
					: ( $secret ? __( 'Leave blank to keep the stored value.', 'acs-courier-for-woocommerce' ) : '' ),
			);
		};

		return array(
			array(
				'title' => __( 'ACS Courier', 'acs-courier-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Credentials are issued by ACS Courier. This plugin sends order delivery details to ACS in order to create shipments.', 'acs-courier-for-woocommerce' ),
				'id'    => 'acs_wc_options',
			),
			$credential( 'company_id', __( 'Company ID', 'acs-courier-for-woocommerce' ), 'text' ),
			$credential( 'company_password', __( 'Company password', 'acs-courier-for-woocommerce' ), 'password' ),
			$credential( 'user_id', __( 'User ID', 'acs-courier-for-woocommerce' ), 'text' ),
			$credential( 'user_password', __( 'User password', 'acs-courier-for-woocommerce' ), 'password' ),
			$credential( 'api_key', __( 'API key', 'acs-courier-for-woocommerce' ), 'password' ),
			array(
				'id'    => 'acs_wc_billing_code',
				'title' => __( 'Billing code', 'acs-courier-for-woocommerce' ),
				'type'  => 'text',
				'value' => $stored['billing_code'] ?? '',
				'desc'  => __( 'The ACS credit code for your account.', 'acs-courier-for-woocommerce' ),
			),
			array(
				'id'    => 'acs_wc_sender_name',
				'title' => __( 'Sender name', 'acs-courier-for-woocommerce' ),
				'type'  => 'text',
				'value' => $stored['sender_name'] ?? '',
				'desc'  => __( 'Printed on every voucher.', 'acs-courier-for-woocommerce' ),
			),
			array(
				'id'      => 'acs_wc_charge_type',
				'title'   => __( 'Who pays', 'acs-courier-for-woocommerce' ),
				'type'    => 'select',
				'value'   => $stored['charge_type'] ?? '2',
				'options' => array(
					'2' => __( 'Sender', 'acs-courier-for-woocommerce' ),
					'4' => __( 'Recipient', 'acs-courier-for-woocommerce' ),
				),
			),
			array(
				'id'    => 'acs_wc_content_type_id',
				'title' => __( 'Default content type (Cyprus)', 'acs-courier-for-woocommerce' ),
				'type'  => 'number',
				'value' => $stored['content_type_id'] ?? '',
				'desc'  => __( 'Required by customs for shipments to Cyprus.', 'acs-courier-for-woocommerce' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'acs_wc_options',
			),
		);
	}

	/**
	 * Persists submitted settings.
	 *
	 * WooCommerce verifies its own settings nonce before this action fires.
	 *
	 * @return void
	 */
	public static function save(): void {
		$stored = self::all();

		foreach ( self::PLAIN_KEYS as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its settings nonce before firing this action.
			if ( isset( $_POST[ 'acs_wc_' . $key ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
				$stored[ $key ] = sanitize_text_field( wp_unslash( $_POST[ 'acs_wc_' . $key ] ) );
			}
		}

		// A blank secret means "leave unchanged", so an admin never has to retype it.
		foreach ( self::SECRET_KEYS as $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
			if ( isset( $_POST[ 'acs_wc_' . $key ] ) && '' !== $_POST[ 'acs_wc_' . $key ] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- See above.
				$stored[ $key ] = sanitize_text_field( wp_unslash( $_POST[ 'acs_wc_' . $key ] ) );
			}
		}

		// Autoload off: credentials must not load on every front-end request.
		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Returns every stored setting.
	 *
	 * @return array<string,string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}
}

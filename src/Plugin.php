<?php
/**
 * Plugin bootstrap.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier;

use AcsCourier\Admin\OrderMetaBox;
use AcsCourier\Admin\Settings;
use AcsCourier\Integration\LockerSelector;
use AcsCourier\Integration\ShippingMethod;
use AcsCourier\Integration\Scheduler;
use AcsCourier\Support\Installer;
use AcsCourier\Support\Requirements;

/**
 * Wires the plugin into WordPress, refusing to run on an unsupported stack.
 */
final class Plugin {

	/**
	 * Guards against double bootstrapping.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Boots the plugin.
	 *
	 * @param string $file Main plugin file.
	 * @return void
	 */
	public static function boot( string $file ): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action(
			'before_woocommerce_init',
			static function () use ( $file ): void {
				if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
						'custom_order_tables',
						$file,
						true
					);
				}
			}
		);

		add_action(
			'plugins_loaded',
			static function () use ( $file ): void {
				$requirements = new Requirements(
					PHP_VERSION,
					get_bloginfo( 'version' ),
					defined( 'WC_VERSION' ) ? WC_VERSION : null
				);

				if ( ! $requirements->isSatisfied() ) {
					add_action(
						'admin_notices',
						static function () use ( $requirements ): void {
							echo '<div class="notice notice-error"><p><strong>'
								. esc_html__( 'ACS Courier for WooCommerce', 'acs-courier-for-woocommerce' )
								. '</strong></p><ul>';
							foreach ( $requirements->unmet() as $problem ) {
								echo '<li>' . esc_html( $problem ) . '</li>';
							}
							echo '</ul></div>';
						}
					);
					return;
				}

				load_plugin_textdomain(
					'acs-courier-for-woocommerce',
					false,
					dirname( plugin_basename( $file ) ) . '/languages'
				);

				Installer::maybeInstall();
				Settings::register();
				OrderMetaBox::register();
				ShippingMethod::register();
				LockerSelector::register();
				Scheduler::register();

				/**
				 * Fires once the plugin has booted successfully.
				 *
				 * @since 0.1.0
				 */
				do_action( 'acs_wc_booted' );
			}
		);
	}
}

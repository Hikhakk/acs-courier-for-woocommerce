<?php
/**
 * Background jobs, run through Action Scheduler.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Integration;

use AcsCourier\Admin\Settings;
use AcsCourier\Admin\SettingsResolver;
use AcsCourier\Api\AcsClient;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\WpHttpTransport;
use AcsCourier\Service\PickupPointRepository;

/**
 * Background jobs, run through Action Scheduler.
 *
 * Action Scheduler ships with WooCommerce and is observable and retryable,
 * which wp-cron is not.
 */
final class Scheduler {

	/**
	 * Hook that refreshes the pickup point cache.
	 */
	public const HOOK_SYNC_POINTS = 'acs_wc_sync_pickup_points';

	/**
	 * Registers hooks and schedules the recurring job.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( self::HOOK_SYNC_POINTS, array( self::class, 'syncPickupPoints' ) );

		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

		if ( ! as_has_scheduled_action( self::HOOK_SYNC_POINTS ) ) {
			as_schedule_recurring_action(
				time() + HOUR_IN_SECONDS,
				DAY_IN_SECONDS,
				self::HOOK_SYNC_POINTS,
				array(),
				'acs-courier'
			);
		}
	}

	/**
	 * Refreshes the pickup point cache for both supported countries.
	 *
	 * @return void
	 */
	public static function syncPickupPoints(): void {
		$settings = Settings::all();
		$creds    = SettingsResolver::resolve( $settings, SettingsResolver::definedConstants() );

		if ( ! $creds->isComplete() ) {
			return;
		}

		$repository = new PickupPointRepository(
			new RetryingClient( new AcsClient( new WpHttpTransport(), $creds ) )
		);

		foreach ( array( 'GR', 'CY' ) as $country ) {
			try {
				$repository->sync( $country );
			} catch ( \Exception $e ) {
				if ( function_exists( 'wc_get_logger' ) ) {
					wc_get_logger()->error(
						sprintf( 'ACS pickup point sync failed for %s: %s', $country, $e->getMessage() ),
						array( 'source' => 'acs-courier' )
					);
				}
			}
		}
	}
}

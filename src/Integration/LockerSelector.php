<?php
/**
 * Lets the customer choose an ACS pickup point at checkout.
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
 * Lets the customer choose an ACS pickup point at checkout.
 *
 * The list is served from the local table, so checkout never waits on ACS.
 */
final class LockerSelector {

	/**
	 * Session and order meta key holding the chosen point.
	 */
	public const FIELD = 'acs_wc_pickup_point';

	/**
	 * Shipping rate id that requires a pickup point.
	 */
	public const LOCKER_RATE = 'acs_courier:locker';

	/**
	 * Hooks the selector into checkout.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action( 'woocommerce_review_order_after_shipping', array( self::class, 'render' ) );
		add_action( 'woocommerce_checkout_process', array( self::class, 'validate' ) );
		add_action( 'woocommerce_checkout_create_order', array( self::class, 'save' ), 10, 2 );
		add_action( 'wp_ajax_acs_wc_points', array( self::class, 'ajaxPoints' ) );
		add_action( 'wp_ajax_nopriv_acs_wc_points', array( self::class, 'ajaxPoints' ) );
	}

	/**
	 * Whether the customer picked the pickup-point rate.
	 *
	 * @return bool
	 */
	private static function lockerChosen(): bool {
		$session = WC()->session;
		if ( null === $session ) {
			return false;
		}

		$chosen = $session->get( 'chosen_shipping_methods' );

		return is_array( $chosen ) && in_array( self::LOCKER_RATE, $chosen, true );
	}

	/**
	 * Renders the selector row inside the order review table.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! self::lockerChosen() ) {
			return;
		}

		$customer = WC()->customer;
		$country  = null !== $customer ? (string) $customer->get_shipping_country() : '';
		$postcode = null !== $customer ? (string) $customer->get_shipping_postcode() : '';
		$selected = (string) WC()->session->get( self::FIELD, '' );

		echo '<tr class="acs-wc-pickup-point"><th>'
			. esc_html__( 'Pickup point', 'acs-courier-for-woocommerce' )
			. '</th><td>';

		echo '<select name="' . esc_attr( self::FIELD ) . '" id="acs-wc-pickup-point" style="width:100%">';
		echo '<option value="">' . esc_html__( 'Choose a pickup point…', 'acs-courier-for-woocommerce' ) . '</option>';

		foreach ( self::points( (string) $country, (string) $postcode ) as $point ) {
			$label = $point['name'];
			if ( '' !== $point['address'] ) {
				$label .= ' — ' . $point['address'];
			}
			if ( isset( $point['distance_km'] ) ) {
				$label .= sprintf( ' (%.1f km)', (float) $point['distance_km'] );
			}

			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( (string) $point['point_id'] ),
				selected( $selected, (string) $point['point_id'], false ),
				esc_html( $label )
			);
		}

		echo '</select>';
		echo '<p class="description">'
			. esc_html__( 'Your parcel waits here for you to collect it.', 'acs-courier-for-woocommerce' )
			. '</p>';
		echo '</td></tr>';
	}

	/**
	 * Returns candidate points near the customer.
	 *
	 * @param string $country  ISO country code.
	 * @param string $postcode Customer postcode.
	 * @return list<array<string,mixed>>
	 */
	private static function points( string $country, string $postcode ): array {
		$repository = new PickupPointRepository( self::client() );

		// Anchor on the customer's own postcode when we know where it is.
		$anchor = self::anchorFor( $country, $postcode );
		if ( null === $anchor ) {
			return array();
		}

		return $repository->nearest( $country, $anchor['lat'], $anchor['lng'], 25, false );
	}

	/**
	 * Finds a coordinate to measure distances from.
	 *
	 * Uses the average position of points sharing the customer's postcode, which
	 * needs no geocoding service and no external request.
	 *
	 * @param string $country  ISO country code.
	 * @param string $postcode Customer postcode.
	 * @return array{lat:float,lng:float}|null
	 */
	private static function anchorFor( string $country, string $postcode ): ?array {
		global $wpdb;

		$table = \AcsCourier\Support\Installer::tableName();

		// A table name cannot be a placeholder; it comes from $wpdb->prefix, which is trusted.
		// Each query is written out in full so the placeholders are visible to review.
		if ( '' !== $postcode ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
			$row = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT AVG(lat) AS lat, AVG(lng) AS lng FROM {$table}
					WHERE country = %s AND lat IS NOT NULL AND postcode = %s",
					$country,
					$postcode
				),
				ARRAY_A
			);

			if ( is_array( $row ) && null !== $row['lat'] ) {
				return array(
					'lat' => (float) $row['lat'],
					'lng' => (float) $row['lng'],
				);
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT AVG(lat) AS lat, AVG(lng) AS lng FROM {$table}
				WHERE country = %s AND lat IS NOT NULL",
				$country
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) || null === $row['lat'] ) {
			return null;
		}

		return array(
			'lat' => (float) $row['lat'],
			'lng' => (float) $row['lng'],
		);
	}

	/**
	 * Rejects checkout when a pickup point is required but not chosen.
	 *
	 * @return void
	 */
	public static function validate(): void {
		if ( ! self::lockerChosen() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		$chosen = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';

		if ( '' === $chosen ) {
			wc_add_notice(
				__( 'Please choose an ACS pickup point for your delivery.', 'acs-courier-for-woocommerce' ),
				'error'
			);
			return;
		}

		// ACS refuses a multi-piece shipment to a pickup point, and needs a mobile
		// number. Surfaced here, while the customer can still change something.
		if ( '' === trim( (string) WC()->customer->get_billing_phone() ) ) {
			wc_add_notice(
				__( 'ACS needs a phone number to deliver to a pickup point.', 'acs-courier-for-woocommerce' ),
				'error'
			);
		}

		WC()->session->set( self::FIELD, $chosen );
	}

	/**
	 * Stores the chosen point on the order.
	 *
	 * @param \WC_Order           $order Order being created.
	 * @param array<string,mixed> $data  Posted checkout data. Unused, but part of
	 *                                   the WooCommerce hook signature.
	 * @return void
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
	 */
	public static function save( $order, $data ): void {
		if ( ! $order instanceof \WC_Order || ! self::lockerChosen() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
		$chosen = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';

		if ( '' !== $chosen ) {
			$order->update_meta_data( self::FIELD, $chosen );
		}
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter

	/**
	 * Returns nearby points as JSON, for progressive enhancement.
	 *
	 * @return void
	 */
	public static function ajaxPoints(): void {
		check_ajax_referer( 'acs_wc_points' );

		$country  = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : '';
		$postcode = isset( $_GET['postcode'] ) ? sanitize_text_field( wp_unslash( $_GET['postcode'] ) ) : '';

		wp_send_json_success( self::points( $country, $postcode ) );
	}

	/**
	 * Builds a configured ACS client.
	 *
	 * @return RetryingClient
	 */
	private static function client(): RetryingClient {
		$settings = Settings::all();

		return new RetryingClient(
			new AcsClient(
				new WpHttpTransport(),
				SettingsResolver::resolve( $settings, SettingsResolver::definedConstants() )
			)
		);
	}
}

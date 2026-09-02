<?php
/**
 * ACS panel on the order edit screen.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Admin;

use AcsCourier\Api\AcsClient;
use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\WpHttpTransport;
use AcsCourier\Integration\WooOrderReader;
use AcsCourier\Mapping\MapperSettings;
use AcsCourier\Mapping\OrderMapper;
use AcsCourier\Service\OrderLock;
use AcsCourier\Service\ShipmentService;

/**
 * Renders the ACS panel and handles voucher creation from an order.
 */
final class OrderMetaBox {

	/**
	 * Order meta key holding the voucher number.
	 */
	public const META_VOUCHER = '_acs_wc_voucher_no';

	/**
	 * Order meta key holding the creation timestamp.
	 */
	public const META_CREATED = '_acs_wc_created_at';

	/**
	 * Admin-post action name.
	 */
	public const ACTION = 'acs_wc_create_voucher';

	/**
	 * Hooks the meta box and its action.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_action(
			'add_meta_boxes',
			static function ( $screen ) {
				if ( ! in_array( $screen, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
					return;
				}
				add_meta_box(
					'acs-wc-shipment',
					__( 'ACS Courier', 'acs-courier-for-woocommerce' ),
					array( self::class, 'render' ),
					$screen,
					'side',
					'default'
				);
			}
		);

		add_action( 'admin_post_' . self::ACTION, array( self::class, 'handle' ) );
	}

	/**
	 * Renders the panel.
	 *
	 * @param mixed $post_or_order Post object on legacy screens, WC_Order under HPOS.
	 * @return void
	 */
	public static function render( $post_or_order ): void {
		$order = $post_or_order instanceof \WC_Order
			? $post_or_order
			: wc_get_order( is_object( $post_or_order ) ? $post_or_order->ID : 0 );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$voucher = (string) $order->get_meta( self::META_VOUCHER );

		if ( '' !== $voucher ) {
			echo '<p><strong>' . esc_html__( 'Voucher', 'acs-courier-for-woocommerce' ) . ':</strong> '
				. esc_html( $voucher ) . '</p>';
			return;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::ACTION . '&order_id=' . $order->get_id() ),
			self::ACTION . '_' . $order->get_id()
		);

		echo '<p><a href="' . esc_url( $url ) . '" class="button button-primary">'
			. esc_html__( 'Create ACS voucher', 'acs-courier-for-woocommerce' )
			. '</a></p>';
	}

	/**
	 * Creates the voucher for an order.
	 *
	 * @return void
	 */
	public static function handle(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified immediately below.

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'acs-courier-for-woocommerce' ) );
		}
		check_admin_referer( self::ACTION . '_' . $order_id );

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'acs-courier-for-woocommerce' ) );
		}

		$lock = new OrderLock();

		// Two guards, because a duplicate voucher is a second real parcel:
		// an existing number, and an atomic lock against concurrent requests.
		if ( '' !== (string) $order->get_meta( self::META_VOUCHER ) ) {
			self::redirect( $order, 'exists' );
		}
		if ( ! $lock->acquire( $order_id ) ) {
			self::redirect( $order, 'busy' );
		}

		try {
			$settings = Settings::all();

			$mapper_settings                       = new MapperSettings();
			$mapper_settings->sender               = $settings['sender_name'] ?? '';
			$mapper_settings->billingCode          = $settings['billing_code'] ?? '';
			$mapper_settings->chargeType           = (int) ( $settings['charge_type'] ?? 2 );
			$mapper_settings->defaultContentTypeId = isset( $settings['content_type_id'] ) && '' !== $settings['content_type_id']
				? (int) $settings['content_type_id']
				: null;
			$mapper_settings->pickupDate           = gmdate( 'Y-m-d' );

			$shipment = OrderMapper::toShipment( WooOrderReader::read( $order ), $mapper_settings );

			$client = new RetryingClient(
				new AcsClient(
					new WpHttpTransport(),
					SettingsResolver::resolve( $settings, SettingsResolver::definedConstants() )
				)
			);

			$voucher = ( new ShipmentService( $client ) )->create( $shipment );

			$order->update_meta_data( self::META_VOUCHER, $voucher );
			$order->update_meta_data( self::META_CREATED, (string) time() );
			$order->add_order_note(
				sprintf(
					/* translators: %s: ACS voucher number. */
					__( 'ACS voucher created: %s', 'acs-courier-for-woocommerce' ),
					$voucher
				)
			);
			$order->save();

			$lock->release( $order_id );
			self::redirect( $order, 'created' );
		} catch ( \InvalidArgumentException $e ) {
			$order->add_order_note(
				sprintf(
					/* translators: %s: list of validation problems. */
					__( 'ACS voucher not created: %s', 'acs-courier-for-woocommerce' ),
					$e->getMessage()
				)
			);
			$order->save();
			$lock->release( $order_id );
			self::redirect( $order, 'invalid' );
		} catch ( AcsException $e ) {
			// ACS messages are often Greek; shown verbatim rather than mistranslated.
			$order->add_order_note(
				sprintf(
					/* translators: %s: message returned by ACS. */
					__( 'ACS rejected the shipment: %s', 'acs-courier-for-woocommerce' ),
					$e->getMessage()
				)
			);
			$order->save();
			$lock->release( $order_id );
			self::redirect( $order, 'failed' );
		}//end try
	}

	/**
	 * Sends the admin back to the order screen.
	 *
	 * @param \WC_Order $order  Order being edited.
	 * @param string    $result Outcome slug.
	 * @return void
	 */
	private static function redirect( \WC_Order $order, string $result ): void {
		wp_safe_redirect( add_query_arg( 'acs_wc_result', $result, $order->get_edit_order_url() ) );
		exit;
	}
}

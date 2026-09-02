<?php
/**
 * Reads the parts of a WooCommerce order the plugin needs.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Integration;

use AcsCourier\Mapping\OrderData;

/**
 * The only place a WC_Order is touched, which keeps OrderMapper pure.
 */
final class WooOrderReader {

	/**
	 * Converts an order into plain data.
	 *
	 * @param \WC_Order $order Order to read.
	 * @return OrderData
	 */
	public static function read( \WC_Order $order ): OrderData {
		$data = new OrderData();

		$data->id                  = (int) $order->get_id();
		$data->name                = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		$data->company             = (string) $order->get_shipping_company();
		$data->address1            = (string) $order->get_shipping_address_1();
		$data->address2            = (string) $order->get_shipping_address_2();
		$data->city                = (string) $order->get_shipping_city();
		$data->postcode            = (string) $order->get_shipping_postcode();
		$data->countryCode         = (string) $order->get_shipping_country();
		$data->email               = (string) $order->get_billing_email();
		$data->customerNote        = (string) $order->get_customer_note();
		$data->weightUnit          = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$data->pickupPointId       = (string) $order->get_meta( LockerSelector::FIELD );
		$data->pickupPointIsLocker = 'yes' === (string) $order->get_meta( LockerSelector::FIELD_IS_LOCKER );

		/**
		 * Filters which payment gateways mean cash on delivery.
		 *
		 * @since 0.4.0
		 *
		 * @param list<string> $gateways Gateway ids treated as COD.
		 */
		$cod_gateways = apply_filters( 'acs_wc_cod_gateways', array( 'cod' ) );

		if ( in_array( (string) $order->get_payment_method(), (array) $cod_gateways, true ) && ! $order->is_paid() ) {
			$data->codAmount = (float) $order->get_total();
		}

		$shipping_phone = method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '';
		$data->phone    = '' !== $shipping_phone ? $shipping_phone : (string) $order->get_billing_phone();

		// Fall back to billing when the order carries no shipping address at all.
		if ( '' === $data->address1 ) {
			$data->name        = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$data->company     = (string) $order->get_billing_company();
			$data->address1    = (string) $order->get_billing_address_1();
			$data->address2    = (string) $order->get_billing_address_2();
			$data->city        = (string) $order->get_billing_city();
			$data->postcode    = (string) $order->get_billing_postcode();
			$data->countryCode = (string) $order->get_billing_country();
		}

		$weight = 0.0;
		$count  = 0;
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product  = $item->get_product();
			$quantity = (int) $item->get_quantity();
			$count   += $quantity;
			if ( $product instanceof \WC_Product && '' !== (string) $product->get_weight() ) {
				$weight += (float) $product->get_weight() * $quantity;
			}
		}

		$data->weight    = $weight;
		$data->itemCount = max( 1, $count );

		return $data;
	}
}

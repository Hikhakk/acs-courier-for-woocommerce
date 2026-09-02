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

		$data->id            = (int) $order->get_id();
		$data->name          = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		$data->company       = (string) $order->get_shipping_company();
		$data->address1      = (string) $order->get_shipping_address_1();
		$data->address2      = (string) $order->get_shipping_address_2();
		$data->city          = (string) $order->get_shipping_city();
		$data->postcode      = (string) $order->get_shipping_postcode();
		$data->countryCode   = (string) $order->get_shipping_country();
		$data->email         = (string) $order->get_billing_email();
		$data->customerNote  = (string) $order->get_customer_note();
		$data->weightUnit    = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$data->pickupPointId = (string) $order->get_meta( LockerSelector::FIELD );

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

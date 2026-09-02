<?php
/**
 * Turns a WooCommerce order (as a plain struct) into a Shipment.
 *
 * Pure by design: no WordPress calls, so every mapping rule is unit-testable.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

use AcsCourier\Domain\Country;
use AcsCourier\Domain\Shipment;
use AcsCourier\Domain\Weight;

/**
 * Turns a WooCommerce order into a Shipment.
 */
final class OrderMapper {

	/** Multipliers to kilograms, keyed by the WooCommerce weight unit. */
	private const TO_KILOGRAMS = array(
		'kg'  => 1.0,
		'g'   => 0.001,
		'lbs' => 0.45359237,
		'oz'  => 0.028349523125,
	);

	/**
	 * To shipment.
	 *
	 * @param OrderData      $order    Order to convert.
	 * @param MapperSettings $settings Merchant configuration.
	 * @return Shipment
	 */
	public static function toShipment( OrderData $order, MapperSettings $settings ): Shipment {
		// Throws for anything ACS cannot ship to, before we build anything else.
		$country = Country::fromCode( $order->countryCode );

		$split = AddressSplitter::split( $order->address1 );

		$shipment                         = new Shipment();
		$shipment->recipientName          = trim( $order->name );
		$shipment->recipientCompany       = trim( $order->company );
		$shipment->recipientAddress       = $split['street'];
		$shipment->recipientAddressNumber = $split['number'];
		$shipment->recipientZip           = trim( $order->postcode );
		// ACS rejects the region inside the address, so the city travels separately.
		$shipment->recipientRegion    = trim( $order->city );
		$shipment->recipientPhone     = trim( $order->phone );
		$shipment->recipientCellPhone = trim( $order->phone );
		$shipment->recipientEmail     = trim( $order->email );
		$shipment->country            = $country;
		$shipment->weight             = self::weight( $order );
		// ACS counts parcels here, not units ordered. One parcel per order for now;
		// sending units would silently invalidate locker delivery.
		$shipment->itemQuantity  = 1;
		$shipment->pickupDate    = $settings->pickupDate;
		$shipment->sender        = $settings->sender;
		$shipment->billingCode   = $settings->billingCode;
		$shipment->chargeType    = $settings->chargeType;
		$shipment->language      = $settings->language;
		$shipment->referenceKey1 = (string) $order->id;
		$shipment->deliveryNotes = self::notes( $order );

		if ( $country->requiresContentType() ) {
			$shipment->contentTypeId = $settings->defaultContentTypeId;
		}

		self::applyPickupPoint( $shipment, $order->pickupPointId );

		return $shipment;
	}

	/**
	 * Weight.
	 *
	 * @param OrderData $order Order.
	 * @return Weight
	 */
	/**
	 * Routes the shipment to a pickup point when the customer chose one.
	 *
	 * @param Shipment $shipment Shipment being built.
	 * @param string   $point_id Chosen point as "STATION:BRANCH".
	 * @return void
	 */
	private static function applyPickupPoint( Shipment $shipment, string $point_id ): void {
		$point_id = trim( $point_id );
		if ( '' === $point_id || false === strpos( $point_id, ':' ) ) {
			return;
		}

		list( $station, $branch ) = explode( ':', $point_id, 2 );
		if ( '' === $station ) {
			return;
		}

		$shipment->stationDestination       = $station;
		$shipment->stationBranchDestination = (int) $branch;

		// REC is the ACS product for collection from a store or Smartpoint.
		if ( ! in_array( 'REC', $shipment->deliveryProducts, true ) ) {
			$shipment->deliveryProducts[] = 'REC';
		}
	}

	/**
	 * Converts the order weight into kilograms.
	 *
	 * @param OrderData $order Order to read.
	 * @return Weight
	 */
	private static function weight( OrderData $order ): Weight {
		$unit       = strtolower( trim( $order->weightUnit ) );
		$multiplier = self::TO_KILOGRAMS[ $unit ] ?? 1.0;

		return Weight::fromKilograms( $order->weight * $multiplier );
	}

	/**
	 * Builds the delivery note.
	 *
	 * ACS has no second address line, so anything there must reach the courier
	 * as a delivery note rather than being silently dropped.
	 *
	 * @param OrderData $order Order to read.
	 * @return string
	 */
	private static function notes( OrderData $order ): string {
		$parts = array();
		if ( '' !== trim( $order->address2 ) ) {
			$parts[] = trim( $order->address2 );
		}
		if ( '' !== trim( $order->customerNote ) ) {
			$parts[] = trim( $order->customerNote );
		}

		return implode( ' — ', $parts );
	}
}

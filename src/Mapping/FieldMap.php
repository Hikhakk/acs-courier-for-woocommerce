<?php
/**
 * The one and only place ACS's field naming - including its misspellings -
 * is allowed to exist.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

use AcsCourier\Domain\Shipment;

/**
 * Translates a Shipment into ACS parameter names, and validates it first.
 */
final class FieldMap {

	public const MAX_PIECES = 99;

	/**
	 * Translates a shipment into ACS create-voucher parameters.
	 *
	 * @param Shipment $s Shipment to translate.
	 * @return array<string,mixed>
	 */
	public static function toCreateVoucherParams( Shipment $s ): array {
		$country = null !== $s->country ? $s->country->code() : '';
		$weight  = null !== $s->weight ? $s->weight->forAcs() : null;

		return array(
			'Pickup_Date'                    => $s->pickupDate,
			'Sender'                         => $s->sender,
			'Recipient_Name'                 => $s->recipientName,
			'Recipient_Address'              => $s->recipientAddress,
			'Recipient_Address_Number'       => $s->recipientAddressNumber,
			'Recipient_Zipcode'              => $s->recipientZip,
			'Recipient_Region'               => $s->recipientRegion,
			'Recipient_Phone'                => $s->recipientPhone,
			'Recipient_Cell_Phone'           => $s->recipientCellPhone,
			'Recipient_Floor'                => null,
			'Recipient_Company_Name'         => '' !== $s->recipientCompany ? $s->recipientCompany : null,
			'Recipient_Country'              => $country,
			'Acs_Station_Destination'        => $s->stationDestination,
			'Acs_Station_Branch_Destination' => $s->stationBranchDestination,
			'Billing_Code'                   => $s->billingCode,
			'Charge_Type'                    => $s->chargeType,
			'Cost_Center_Code'               => null,
			'Item_Quantity'                  => $s->itemQuantity,
			'Weight'                         => $weight,
			'Dimension_X_In_Cm'              => $s->lengthCm,
			'Dimension_Y_in_Cm'              => $s->widthCm,
			'Dimension_Z_in_Cm'              => $s->heightCm,
			// ACS spells these with a doubled m. Do not "fix" them.
			'Cod_Ammount'                    => $s->codAmount,
			'Cod_Payment_Way'                => $s->codPaymentWay,
			'Insurance_Ammount'              => $s->insuranceAmount,
			'Acs_Delivery_Products'          => array() === $s->deliveryProducts
				? null
				: implode( ',', $s->deliveryProducts ),
			'Delivery_Notes'                 => '' !== $s->deliveryNotes ? $s->deliveryNotes : null,
			'Appointment_Until_Time'         => null,
			'Recipient_Email'                => '' !== $s->recipientEmail ? $s->recipientEmail : null,
			'Reference_Key1'                 => '' !== $s->referenceKey1 ? $s->referenceKey1 : null,
			'Reference_Key2'                 => '' !== $s->referenceKey2 ? $s->referenceKey2 : null,
			'With_Return_Voucher'            => null,
			'Content_Type_ID'                => $s->contentTypeId,
			'Language'                       => $s->language,
		);
	}

	/**
	 * Local pre-flight validation, so we never spend an API call on data ACS will reject.
	 *
	 * @param \AcsCourier\Domain\Shipment $s Shipment to check.
	 * @return list<string> Human-readable problems; empty means valid.
	 */
	public static function validate( Shipment $s ): array {
		$problems = array();

		if ( '' === trim( $s->recipientName ) ) {
			$problems[] = 'The recipient name is required.';
		}
		if ( '' === trim( $s->recipientAddress ) ) {
			$problems[] = 'The recipient address is required.';
		}
		if ( null === $s->country ) {
			$problems[] = 'A destination country of GR or CY is required.';
			return $problems;
		}
		if ( ! $s->country->isValidZip( $s->recipientZip ) ) {
			$problems[] = sprintf(
				'The postcode "%s" is not a valid %s postcode (%d digits expected).',
				$s->recipientZip,
				$s->country->code(),
				$s->country->zipLength()
			);
		}
		if ( $s->country->requiresContentType() && null === $s->contentTypeId ) {
			$problems[] = 'Shipments to Cyprus must declare a content type, or customs will hold them.';
		}
		if ( $s->itemQuantity < 1 || $s->itemQuantity > self::MAX_PIECES ) {
			$problems[] = sprintf( 'Item quantity must be between 1 and %d.', self::MAX_PIECES );
		}
		if ( null !== $s->weight && $s->weight->isAboveMaximum() ) {
			$problems[] = 'The shipment weight exceeds the 999 kg maximum.';
		}
		if ( ! in_array( $s->chargeType, array( 2, 4 ), true ) ) {
			$problems[] = 'Charge type must be 2 (sender) or 4 (recipient).';
		}
		if ( '' === trim( $s->billingCode ) ) {
			$problems[] = 'A billing code is required.';
		}
		if ( '' === trim( $s->pickupDate ) ) {
			$problems[] = 'A pickup date is required.';
		}

		if ( $s->isToPickupPoint() ) {
			if ( $s->itemQuantity > 1 ) {
				$problems[] = 'ACS cannot deliver a multi-piece shipment to a pickup point.';
			}
			if ( '' === trim( $s->recipientCellPhone ) ) {
				$problems[] = 'Delivery to a pickup point requires a recipient mobile number.';
			}
			if ( null !== $s->codAmount && '' === trim( $s->recipientEmail ) ) {
				$problems[] = 'Cash on delivery to a pickup point requires a recipient email address.';
			}
		}

		return $problems;
	}
}

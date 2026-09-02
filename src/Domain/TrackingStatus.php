<?php
/**
 * A shipment's current state, as ACS reports it.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

/**
 * A shipment's current state, as ACS reports it.
 */
final class TrackingStatus {

	/**
	 * ACS status code for a delivered shipment.
	 */
	public const DELIVERED = 4;

	/**
	 * ACS status code for a shipment on its way back to the sender.
	 */
	public const RETURNING = 6;

	/**
	 * ACS status code for a shipment returned to the sender.
	 */
	public const RETURNED = 7;

	/**
	 * Non-delivery codes ACS documents, in plain English.
	 */
	private const NON_DELIVERY = array(
		'AD1' => 'Held for office pickup',
		'AD3' => 'Force majeure',
		'AD8' => 'Collected from an ACS shop at the sender\'s request',
		'AP1' => 'Delivery refused, due to the charge',
		'AP2' => 'Recipient unable to pay',
		'AP3' => 'Shipment not acceptable',
		'AP4' => 'Recipient deceased',
		'AS1' => 'Absent',
		'DP1' => 'Area difficult to access',
		'LS1' => 'Consignee unknown',
		'LS2' => 'In transit to destination',
		'LS3' => 'Address wrong or incomplete',
		'PA1' => 'New delivery date set by the sender',
		'PA2' => 'New delivery date set by the recipient',
		'PA4' => 'Rescheduled or redirected',
	);

	/**
	 * ACS shipment status code.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * 1 when delivered.
	 *
	 * @var int
	 */
	private int $delivery_flag;

	/**
	 * 1 when returned to the sender.
	 *
	 * @var int
	 */
	private int $returned_flag;

	/**
	 * ACS non-delivery reason code, if any.
	 *
	 * @var string
	 */
	private string $non_delivery_code;

	/**
	 * Records the raw ACS values.
	 *
	 * @param int    $status            ACS shipment status code.
	 * @param int    $delivery_flag     1 when delivered.
	 * @param int    $returned_flag     1 when returned to the sender.
	 * @param string $non_delivery_code ACS non-delivery reason code, if any.
	 */
	public function __construct( int $status, int $delivery_flag, int $returned_flag, string $non_delivery_code ) {
		$this->status            = $status;
		$this->delivery_flag     = $delivery_flag;
		$this->returned_flag     = $returned_flag;
		$this->non_delivery_code = strtoupper( trim( $non_delivery_code ) );
	}

	/**
	 * Whether the parcel reached the recipient.
	 *
	 * @return bool
	 */
	public function isDelivered(): bool {
		// ACS sets delivery_flag to 1 when a *returned* parcel reaches the sender
		// as well, so delivery to the recipient needs both signals to agree.
		return self::DELIVERED === $this->status
			&& 1 === $this->delivery_flag
			&& ! $this->isReturned();
	}

	/**
	 * Whether the parcel is on its way back to the sender.
	 *
	 * @return bool
	 */
	public function isReturning(): bool {
		return self::RETURNING === $this->status;
	}

	/**
	 * Whether the parcel is back with the sender.
	 *
	 * @return bool
	 */
	public function isReturned(): bool {
		return self::RETURNED === $this->status || 1 === $this->returned_flag;
	}

	/**
	 * Whether the parcel is still moving.
	 *
	 * @return bool
	 */
	public function isInTransit(): bool {
		return ! $this->isDelivered() && ! $this->isReturning() && ! $this->isReturned();
	}

	/**
	 * The ACS status code.
	 *
	 * @return int
	 */
	public function code(): int {
		return $this->status;
	}

	/**
	 * A readable non-delivery reason, or an empty string when there is none.
	 *
	 * An unrecognised code is reported verbatim rather than swallowed, because
	 * ACS may add codes without notice.
	 *
	 * @return string
	 */
	public function nonDeliveryReason(): string {
		if ( '' === $this->non_delivery_code ) {
			return '';
		}

		return self::NON_DELIVERY[ $this->non_delivery_code ]
			?? sprintf( 'Unrecognised ACS reason code %s', $this->non_delivery_code );
	}
}

<?php
/**
 * Creates ACS vouchers from shipments.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Domain\Shipment;
use AcsCourier\Mapping\FieldMap;

/**
 * Creates ACS vouchers from shipments.
 */
final class ShipmentService {

	public const ALIAS_CREATE = 'ACS_Create_Voucher';

	/**
	 * ACS client used to send the request.
	 *
	 * @var RetryingClient
	 */
	private RetryingClient $client;

	/**
	 * __construct.
	 *
	 * @param RetryingClient $client Client.
	 */
	public function __construct( RetryingClient $client ) {
		$this->client = $client;
	}

	/**
	 * Creates a voucher and returns its number.
	 *
	 * @param \AcsCourier\Domain\Shipment $shipment Shipment to send.
	 * @return string
	 * @throws \InvalidArgumentException When the shipment is locally invalid.
	 * @throws AcsException When ACS rejects it.
	 */
	public function create( Shipment $shipment ): string {
		$problems = FieldMap::validate( $shipment );
		if ( array() !== $problems ) {
			throw new \InvalidArgumentException( implode( ' ', $problems ) );
		}

		$response = $this->client->call( self::ALIAS_CREATE, FieldMap::toCreateVoucherParams( $shipment ) );

		$values  = $response['ACSValueOutput'] ?? array();
		$voucher = is_array( $values ) && isset( $values[0]['Voucher_No'] )
			? trim( (string) $values[0]['Voucher_No'] )
			: '';

		if ( '' === $voucher ) {
			throw AcsException::business(
				'ACS accepted the request but returned no voucher number.',
				self::ALIAS_CREATE
			);
		}

		return $voucher;
	}
}

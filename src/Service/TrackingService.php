<?php
/**
 * Reads shipment status and history from ACS.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Domain\TrackingStatus;

/**
 * Reads shipment status and history from ACS.
 */
final class TrackingService {

	/**
	 * ACS method returning the latest status.
	 */
	public const ALIAS_SUMMARY = 'ACS_Trackingsummary';

	/**
	 * ACS method returning the full checkpoint history.
	 */
	public const ALIAS_DETAILS = 'ACS_TrackingDetails';

	/**
	 * Client used to reach ACS.
	 *
	 * @var RetryingClient
	 */
	private RetryingClient $client;

	/**
	 * Stores the client.
	 *
	 * @param RetryingClient $client Client used to reach ACS.
	 */
	public function __construct( RetryingClient $client ) {
		$this->client = $client;
	}

	/**
	 * Returns the latest status for a voucher.
	 *
	 * ACS knows nothing about a voucher until the pickup list has been issued and
	 * the parcel has entered its network, so a freshly created voucher reports
	 * "No Acs shipment found". That is expected, not a failure of this plugin.
	 *
	 * @param string $voucher_no Voucher number.
	 * @return TrackingStatus
	 * @throws AcsException If ACS does not recognise the voucher or returns nothing.
	 */
	public function summary( string $voucher_no ): TrackingStatus {
		$response = $this->client->call(
			self::ALIAS_SUMMARY,
			array(
				'Voucher_No' => $voucher_no,
				'Language'   => 'EN',
			)
		);

		$rows = $response['ACSTableOutput']['Table_Data'] ?? array();
		if ( ! is_array( $rows ) || array() === $rows || ! isset( $rows[0] ) ) {
			// An empty result is not "in transit"; it means ACS knows nothing about it.
			throw AcsException::business(
				'ACS returned no tracking information for this voucher.',
				self::ALIAS_SUMMARY,
				$response
			);
		}

		$row = $rows[0];

		return new TrackingStatus(
			isset( $row['shipment_status'] ) ? (int) $row['shipment_status'] : 0,
			isset( $row['delivery_flag'] ) ? (int) $row['delivery_flag'] : 0,
			isset( $row['returned_flag'] ) ? (int) $row['returned_flag'] : 0,
			isset( $row['non_delivery_reason_code'] ) ? (string) $row['non_delivery_reason_code'] : ''
		);
	}

	/**
	 * Returns the checkpoint history for a voucher, oldest first.
	 *
	 * @param string $voucher_no Voucher number.
	 * @return list<array{date:string,action:string,location:string,notes:string}>
	 * @throws AcsException If ACS rejects the request.
	 */
	public function details( string $voucher_no ): array {
		$response = $this->client->call(
			self::ALIAS_DETAILS,
			array(
				'Voucher_No' => $voucher_no,
				'Language'   => 'EN',
			)
		);

		$rows = $response['ACSTableOutput']['Table_Data'] ?? array();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$events = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$events[] = array(
				'date'     => isset( $row['checkpoint_date_time'] ) ? (string) $row['checkpoint_date_time'] : '',
				'action'   => isset( $row['checkpoint_action'] ) ? trim( (string) $row['checkpoint_action'] ) : '',
				'location' => isset( $row['checkpoint_location'] ) ? trim( (string) $row['checkpoint_location'] ) : '',
				'notes'    => isset( $row['checkpoint_notes'] ) ? trim( (string) $row['checkpoint_notes'] ) : '',
			);
		}

		return $events;
	}
}

<?php
/**
 * Issues and prints the ACS pickup list.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;

/**
 * Issues and prints the ACS pickup list.
 *
 * Issuing the list is mandatory: until it exists, ACS does not recognise the
 * barcodes on printed vouchers. Once issued, its vouchers can never be deleted.
 */
final class PickupListService {

	/**
	 * ACS method that issues the list.
	 */
	public const ALIAS_ISSUE = 'ACS_Issue_Pickup_List';

	/**
	 * ACS method that prints the list.
	 */
	public const ALIAS_PRINT = 'ACS_Print_Pickup_List';

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
	 * Issues the pickup list for a date and returns its number.
	 *
	 * @param string $pickup_date Date the vouchers were created, as YYYY-MM-DD.
	 * @param int    $my_data     0 finalises every user's shipments, 1 only the current user's.
	 * @return string
	 * @throws UnprintedVouchersException If any voucher has not been printed.
	 * @throws AcsException If ACS rejects the request.
	 */
	public function issue( string $pickup_date, int $my_data = 0 ): string {
		try {
			$response = $this->client->call(
				self::ALIAS_ISSUE,
				array(
					'Pickup_Date' => $pickup_date,
					'MyData'      => $my_data,
					'Language'    => 'EN',
				)
			);
		} catch ( AcsException $e ) {
			// ACS reports unprinted vouchers as an error that still carries the
			// list of what needs printing. That is actionable, not a dead end.
			$unprinted = $this->unprintedVouchers( $e->response() );
			if ( array() !== $unprinted ) {
				throw new UnprintedVouchersException( $unprinted );
			}
			throw $e;
		}

		$values = $response['ACSValueOutput'][0] ?? array();

		$unprinted = isset( $values['Unprinted_Found'] ) ? (int) $values['Unprinted_Found'] : 0;
		if ( $unprinted > 0 ) {
			throw new UnprintedVouchersException( $this->unprintedVouchers( $response ) );
		}

		$number = isset( $values['PickupList_No'] ) ? trim( (string) $values['PickupList_No'] ) : '';
		if ( '' === $number ) {
			throw AcsException::business(
				'ACS accepted the request but returned no pickup list number.',
				self::ALIAS_ISSUE
			);
		}

		return $number;
	}

	/**
	 * Fetches the printable pickup list.
	 *
	 * @param string $pickup_list_no Number returned by issue().
	 * @param string $pickup_date    Date the list covers, as YYYY-MM-DD.
	 * @return string Raw PDF bytes.
	 * @throws AcsException If ACS returns no document.
	 */
	public function printList( string $pickup_list_no, string $pickup_date ): string {
		$response = $this->client->call(
			self::ALIAS_PRINT,
			array(
				'Mass_Number' => $pickup_list_no,
				'Pickup_Date' => $pickup_date,
				'Language'    => 'EN',
			)
		);

		$objects = $response['ACSObjectOutput'] ?? array();
		if ( is_array( $objects ) ) {
			foreach ( $objects as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( $row as $encoded ) {
					if ( ! is_string( $encoded ) || '' === $encoded ) {
						continue;
					}
					$decoded = base64_decode( $encoded, true );
					if ( false !== $decoded ) {
						return $decoded;
					}
				}
			}
		}

		throw AcsException::business( 'ACS returned no pickup list document.', self::ALIAS_PRINT );
	}

	/**
	 * Extracts the unprinted voucher numbers ACS listed.
	 *
	 * @param array<string,mixed> $response Contents of ACSOutputResponce.
	 * @return list<string>
	 */
	private function unprintedVouchers( array $response ): array {
		$rows = $response['ACSTableOutput']['Table_Data'] ?? array();
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$vouchers = array();
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['Unprinted_Vouchers'] ) ) {
				$vouchers[] = trim( (string) $row['Unprinted_Vouchers'] );
			}
		}

		return $vouchers;
	}
}

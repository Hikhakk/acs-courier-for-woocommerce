<?php
/**
 * Fetches printable voucher labels from ACS.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;

/**
 * Fetches printable voucher labels from ACS.
 */
final class LabelService {

	/**
	 * ACS method name.
	 */
	public const ALIAS_PRINT = 'ACS_Print_Voucher_V2';

	/**
	 * Thermal printer output.
	 */
	public const PRINT_THERMAL = 1;

	/**
	 * A4 laser output, three labels to a page.
	 */
	public const PRINT_LASER = 2;

	/**
	 * ACS accepts at most ten vouchers in one print call.
	 */
	public const MAX_PER_CALL = 10;

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
	 * Fetches label PDFs for the given vouchers.
	 *
	 * @param list<string> $vouchers       Voucher numbers, at most ten.
	 * @param int          $print_type     Self::PRINT_THERMAL or self::PRINT_LASER.
	 * @param int          $start_position Label position on an A4 sheet, 1-3. Laser only.
	 * @return array<string,string> Raw PDF bytes keyed by voucher number.
	 * @throws \InvalidArgumentException If the arguments are outside what ACS accepts. An
	 *                                   AcsException surfaces from decode() when ACS rejects
	 *                                   the request or returns no document.
	 */
	public function fetch( array $vouchers, int $print_type, int $start_position = 1 ): array {
		if ( array() === $vouchers ) {
			throw new \InvalidArgumentException( 'At least one voucher number is required.' );
		}
		if ( count( $vouchers ) > self::MAX_PER_CALL ) {
			throw new \InvalidArgumentException(
				sprintf( 'ACS prints at most %d vouchers per call.', self::MAX_PER_CALL )
			);
		}
		if ( ! in_array( $print_type, array( self::PRINT_THERMAL, self::PRINT_LASER ), true ) ) {
			throw new \InvalidArgumentException( 'Print type must be 1 (thermal) or 2 (laser).' );
		}
		// Start position only has meaning on an A4 sheet, where three labels fit.
		if ( self::PRINT_LASER === $print_type && ( $start_position < 1 || $start_position > 3 ) ) {
			throw new \InvalidArgumentException( 'Start position must be between 1 and 3 for laser printing.' );
		}

		$response = $this->client->call(
			self::ALIAS_PRINT,
			array(
				'Voucher_No'     => implode( ',', $vouchers ),
				'Print_Type'     => $print_type,
				'Start_Position' => $start_position,
				'Language'       => 'EN',
			)
		);

		return $this->decode( $response );
	}

	/**
	 * Turns the base64 document payload into raw PDF bytes.
	 *
	 * ACS nests the document inside ACSValueOutput rather than at the top level,
	 * as an object carrying Voucher_No alongside PDFData. Recorded from the live
	 * API; the documentation does not describe this shape.
	 *
	 * @param array<string,mixed> $response Contents of ACSOutputResponce.
	 * @return array<string,string>
	 * @throws AcsException If no document was returned.
	 */
	private function decode( array $response ): array {
		$labels = array();

		foreach ( (array) ( $response['ACSValueOutput'] ?? array() ) as $entry ) {
			if ( ! is_array( $entry ) || ! isset( $entry['ACSObjectOutput'] ) ) {
				continue;
			}

			$object = $entry['ACSObjectOutput'];
			if ( ! is_array( $object ) ) {
				continue;
			}

			// A single document arrives as one object; several arrive as a list.
			$documents = isset( $object['PDFData'] ) ? array( $object ) : $object;

			foreach ( (array) $documents as $document ) {
				if ( ! is_array( $document ) || ! isset( $document['PDFData'] ) ) {
					continue;
				}

				$pdf = base64_decode( (string) $document['PDFData'], true );
				if ( false === $pdf || '' === $pdf ) {
					continue;
				}

				$voucher            = isset( $document['Voucher_No'] ) ? (string) $document['Voucher_No'] : '';
				$labels[ $voucher ] = $pdf;
			}
		}//end foreach

		if ( array() === $labels ) {
			throw AcsException::business(
				'ACS returned no label document. Vouchers must exist and not yet be on a pickup list.',
				self::ALIAS_PRINT
			);
		}

		return $labels;
	}
}

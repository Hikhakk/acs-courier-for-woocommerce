<?php
/**
 * Sends ACS requests through the WordPress HTTP API.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * Sends ACS requests through the WordPress HTTP API.
 */
final class WpHttpTransport implements Transport {

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private int $timeout;

	/**
	 * Sets the request timeout.
	 *
	 * @param int $timeout_seconds Seconds to wait before giving up.
	 */
	public function __construct( int $timeout_seconds = 45 ) {
		$this->timeout = $timeout_seconds;
	}

	/**
	 * Posts a JSON payload to ACS.
	 *
	 * @param string               $url     Endpoint to post to.
	 * @param array<string,mixed>  $payload Request body.
	 * @param array<string,string> $headers Request headers.
	 * @return TransportResponse
	 * @throws TransportFailure If the request could not be encoded or sent.
	 */
	public function post( string $url, array $payload, array $headers ): TransportResponse {
		$encoded = wp_json_encode( $payload );
		if ( false === $encoded ) {
			throw new TransportFailure( 'Could not encode the ACS request payload.' );
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => $encoded,
				'timeout' => $this->timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new TransportFailure( $response->get_error_message() );
		}

		return new TransportResponse(
			(int) wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_body( $response )
		);
	}
}

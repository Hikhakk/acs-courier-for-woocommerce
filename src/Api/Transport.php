<?php
/**
 * Sends a JSON payload to ACS and returns the raw response.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * Sends a JSON payload to ACS and returns the raw response.
 */
interface Transport {

	/**
	 * Sends a JSON payload to ACS.
	 *
	 * @param string               $url     Endpoint to post to.
	 * @param array<string,mixed>  $payload Request body, encoded as JSON.
	 * @param array<string,string> $headers Request headers.
	 * @return TransportResponse
	 * @throws TransportFailure On network-level failure.
	 */
	public function post( string $url, array $payload, array $headers ): TransportResponse;
}

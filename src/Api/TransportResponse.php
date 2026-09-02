<?php
/**
 * A raw HTTP response from ACS: status code and body.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * A raw HTTP response from ACS: status code and body.
 */
final class TransportResponse {

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	public int $status;
	/**
	 * Raw response body.
	 *
	 * @var string
	 */
	public string $body;

	/**
	 * __construct.
	 *
	 * @param int    $status Status.
	 * @param string $body Body.
	 */
	public function __construct( int $status, string $body ) {
		$this->status = $status;
		$this->body   = $body;
	}
}

<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class TransportResponse {

	public int $status;
	public string $body;

	public function __construct( int $status, string $body ) {
		$this->status = $status;
		$this->body   = $body;
	}
}

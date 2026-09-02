<?php
/**
 * Test double: replays queued responses and records requests.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

/**
 * Test double that replays queued responses and records requests.
 */
final class ArrayTransport implements Transport {

	/**
	 * Responses still to be returned.
	 *
	 * @var list<TransportResponse>
	 */
	private array $queue;

	/**
	 * Every request that was sent.
	 *
	 * @var list<array{url:string,payload:array<string,mixed>,headers:array<string,string>}>
	 */
	private array $requests = array();

	/**
	 * Queues the responses this transport will return.
	 *
	 * @param array<int,TransportResponse> $queue Responses in the order they should be returned.
	 */
	public function __construct( array $queue ) {
		$this->queue = $queue;
	}

	/**
	 * Records the request and returns the next queued response.
	 *
	 * @param string               $url     Endpoint to post to.
	 * @param array<string,mixed>  $payload Request body.
	 * @param array<string,string> $headers Request headers.
	 * @return TransportResponse
	 * @throws \RuntimeException If the queue is exhausted.
	 */
	public function post( string $url, array $payload, array $headers ): TransportResponse {
		$this->requests[] = array(
			'url'     => $url,
			'payload' => $payload,
			'headers' => $headers,
		);

		if ( array() === $this->queue ) {
			throw new \RuntimeException( 'ArrayTransport queue exhausted.' );
		}

		return array_shift( $this->queue );
	}

	/**
	 * Returns every request that was sent.
	 *
	 * @return list<array{url:string,payload:array<string,mixed>,headers:array<string,string>}>
	 */
	public function requests(): array {
		return $this->requests;
	}
}

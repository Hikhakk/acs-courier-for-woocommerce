<?php
/**
 * Framework-agnostic ACS REST client.
 *
 * Deliberately has no WordPress dependency so the whole of ACS's contract
 * can be regression-tested without a WordPress bootstrap.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class AcsClient {

	public const ENDPOINT = 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest';

	/**
	 * ACS nests business errors under several spellings depending on the method.
	 */
	private const NESTED_ERROR_KEYS = array( 'Error_Message', 'error_message', 'Error_msg', 'error_msg' );

	private Transport $transport;
	private Credentials $credentials;
	private string $endpoint;

	public function __construct( Transport $transport, Credentials $credentials, string $endpoint = self::ENDPOINT ) {
		$this->transport   = $transport;
		$this->credentials = $credentials;
		$this->endpoint    = $endpoint;
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed> Contents of ACSOutputResponce.
	 * @throws AcsException
	 */
	public function call( string $alias, array $params = array() ): array {
		$payload = array(
			'ACSAlias'           => $alias,
			'ACSInputParameters' => array_merge( $this->credentials->toArray(), $params ),
		);

		try {
			$raw = $this->transport->post( $this->endpoint, $payload, $this->headers() );
		} catch ( TransportFailure $e ) {
			throw AcsException::transport( $e->getMessage(), $alias );
		}

		return $this->parse( $alias, $raw );
	}

	/** @return array<string,string> */
	private function headers(): array {
		return array(
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
			'AcsApiKey'    => $this->credentials->apiKey(),
		);
	}

	/**
	 * @return array<string,mixed>
	 * @throws AcsException
	 */
	private function parse( string $alias, TransportResponse $raw ): array {
		if ( 403 === $raw->status ) {
			throw AcsException::auth( $alias );
		}
		if ( 406 === $raw->status ) {
			throw AcsException::rateLimited( $alias );
		}
		if ( $raw->status >= 500 ) {
			throw AcsException::transport( 'HTTP ' . $raw->status . ' from ACS.', $alias );
		}
		if ( 200 !== $raw->status ) {
			throw AcsException::transport( 'Unexpected HTTP ' . $raw->status . ' from ACS.', $alias );
		}

		$data = json_decode( $raw->body, true );
		if ( ! is_array( $data ) ) {
			throw AcsException::malformed( $alias );
		}

		// Channel 1: the documented flag.
		if ( ! empty( $data['ACSExecution_HasError'] ) ) {
			$message = isset( $data['ACSExecutionErrorMessage'] ) && '' !== $data['ACSExecutionErrorMessage']
				? (string) $data['ACSExecutionErrorMessage']
				: 'ACS reported an unspecified error.';
			throw AcsException::business( $message, $alias );
		}

		// Note the misspelling: it is ACS's, not ours. Contained here.
		$response = isset( $data['ACSOutputResponce'] ) && is_array( $data['ACSOutputResponce'] )
			? $data['ACSOutputResponce']
			: array();

		// Channel 2: the silent one. HTTP 200 + HasError false + a real error nested here.
		$this->assertNoNestedError( $alias, $response );

		return $response;
	}

	/**
	 * @param array<string,mixed> $response
	 * @throws AcsException
	 */
	private function assertNoNestedError( string $alias, array $response ): void {
		$values = $response['ACSValueOutput'] ?? null;
		if ( ! is_array( $values ) || ! isset( $values[0] ) || ! is_array( $values[0] ) ) {
			return;
		}

		foreach ( self::NESTED_ERROR_KEYS as $key ) {
			if ( ! array_key_exists( $key, $values[0] ) ) {
				continue;
			}
			$message = $values[0][ $key ];
			if ( null === $message || '' === $message ) {
				continue;
			}
			throw AcsException::business( (string) $message, $alias );
		}
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<int,array<string,mixed>>
	 */
	public function valueOutput( array $response ): array {
		$values = $response['ACSValueOutput'] ?? array();
		return is_array( $values ) ? $values : array();
	}

	/**
	 * @param array<string,mixed> $response
	 * @return array<int,array<string,mixed>>
	 */
	public function tableData( array $response ): array {
		$table = $response['ACSTableOutput'] ?? array();
		if ( ! is_array( $table ) ) {
			return array();
		}
		$rows = $table['Table_Data'] ?? array();
		return is_array( $rows ) ? $rows : array();
	}
}

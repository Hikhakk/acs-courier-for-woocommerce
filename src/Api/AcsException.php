<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class AcsException extends \RuntimeException {

	public const KIND_BUSINESS     = 'business';
	public const KIND_AUTH         = 'auth';
	public const KIND_RATE_LIMITED = 'rate_limited';
	public const KIND_MALFORMED    = 'malformed';
	public const KIND_TRANSPORT    = 'transport';

	private string $alias;
	private string $kind;

	private function __construct( string $message, string $alias, string $kind ) {
		parent::__construct( $message );
		$this->alias = $alias;
		$this->kind  = $kind;
	}

	public static function business( string $message, string $alias ): self {
		return new self( $message, $alias, self::KIND_BUSINESS );
	}

	public static function auth( string $alias ): self {
		return new self( 'ACS rejected the API key or credentials.', $alias, self::KIND_AUTH );
	}

	public static function rateLimited( string $alias ): self {
		return new self( 'ACS rate limit exceeded.', $alias, self::KIND_RATE_LIMITED );
	}

	public static function malformed( string $alias ): self {
		return new self( 'ACS returned a response that could not be parsed.', $alias, self::KIND_MALFORMED );
	}

	public static function transport( string $message, string $alias ): self {
		return new self( $message, $alias, self::KIND_TRANSPORT );
	}

	public function alias(): string {
		return $this->alias;
	}

	public function kind(): string {
		return $this->kind;
	}

	public function isRetryable(): bool {
		return in_array( $this->kind, array( self::KIND_RATE_LIMITED, self::KIND_TRANSPORT ), true );
	}
}

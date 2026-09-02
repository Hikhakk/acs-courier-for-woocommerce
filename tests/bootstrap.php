<?php
/**
 * PHPUnit bootstrap.
 *
 * Polyfills the handful of WordPress functions that plugin code legitimately
 * uses for translation, so unit tests still run without loading WordPress.
 *
 * @package AcsCourier
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation polyfill: returns the string unchanged.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Escaping translation polyfill.
	 *
	 * @param string $text   Text to translate.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function esc_html__( string $text, string $domain = 'default' ): string { // phpcs:ignore
		return $text;
	}
}

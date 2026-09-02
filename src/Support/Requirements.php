<?php
/**
 * Checks the host platform meets the plugin's minimum versions.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Support;

/**
 * Checks the host platform meets the plugin's minimum versions.
 */
final class Requirements {

	/**
	 * PHP version of the host.
	 *
	 * @var string
	 */
	private string $php_version;

	/**
	 * WordPress version of the host.
	 *
	 * @var string
	 */
	private string $wp_version;

	/**
	 * WooCommerce version, or null when it is not active.
	 *
	 * @var string|null
	 */
	private ?string $wc_version;

	/**
	 * Records the versions to check.
	 *
	 * @param string      $php_version PHP version of the host.
	 * @param string      $wp_version  WordPress version of the host.
	 * @param string|null $wc_version  WooCommerce version, or null when inactive.
	 */
	public function __construct( string $php_version, string $wp_version, ?string $wc_version ) {
		$this->php_version = $php_version;
		$this->wp_version  = $wp_version;
		$this->wc_version  = $wc_version;
	}

	/**
	 * Lists every requirement the host fails, not merely the first.
	 *
	 * @return list<string>
	 */
	public function unmet(): array {
		$problems = array();

		if ( version_compare( $this->php_version, Version::MIN_PHP, '<' ) ) {
			$problems[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'PHP %1$s or newer is required; this site runs %2$s.', 'acs-courier-for-woocommerce' ),
				Version::MIN_PHP,
				$this->php_version
			);
		}
		if ( version_compare( $this->wp_version, Version::MIN_WP, '<' ) ) {
			$problems[] = sprintf(
				/* translators: 1: required WordPress version, 2: current WordPress version. */
				__( 'WordPress %1$s or newer is required; this site runs %2$s.', 'acs-courier-for-woocommerce' ),
				Version::MIN_WP,
				$this->wp_version
			);
		}
		if ( null === $this->wc_version ) {
			$problems[] = __( 'WooCommerce is required but is not active.', 'acs-courier-for-woocommerce' );
		} elseif ( version_compare( $this->wc_version, Version::MIN_WC, '<' ) ) {
			$problems[] = sprintf(
				/* translators: 1: required WooCommerce version, 2: current WooCommerce version. */
				__( 'WooCommerce %1$s or newer is required; this site runs %2$s.', 'acs-courier-for-woocommerce' ),
				Version::MIN_WC,
				$this->wc_version
			);
		}

		return $problems;
	}

	/**
	 * Whether every requirement is met.
	 *
	 * @return bool
	 */
	public function isSatisfied(): bool {
		return array() === $this->unmet();
	}
}

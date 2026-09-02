<?php
/**
 * ACS shipping method for WooCommerce.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Integration;

use AcsCourier\Admin\Settings;
use AcsCourier\Admin\SettingsResolver;
use AcsCourier\Api\AcsClient;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Api\WpHttpTransport;
use AcsCourier\Domain\Country;
use AcsCourier\Domain\Weight;
use AcsCourier\Service\RateResolver;
use AcsCourier\Service\RateTable;

/**
 * Offers ACS home delivery and pickup-point rates at checkout.
 */
final class ShippingMethod extends \WC_Shipping_Method {

	/**
	 * Registers the method with WooCommerce.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter(
			'woocommerce_shipping_methods',
			static function ( $methods ) {
				$methods['acs_courier'] = self::class;
				return $methods;
			}
		);
	}

	/**
	 * Sets up the method.
	 *
	 * @param int $instance_id Shipping zone instance id.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id                 = 'acs_courier';
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = __( 'ACS Courier', 'acs-courier-for-woocommerce' );
		$this->method_description = __( 'Rates for ACS home delivery and pickup points.', 'acs-courier-for-woocommerce' );
		$this->supports           = array( 'shipping-zones', 'instance-settings', 'instance-settings-modal' );

		$this->init();
	}

	/**
	 * Loads settings.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->init_form_fields();
		$this->init_settings();

		$this->title   = $this->get_option( 'title', __( 'ACS Courier', 'acs-courier-for-woocommerce' ) );
		$this->enabled = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Declares the instance settings.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->instance_form_fields = array(
			'title'        => array(
				'title'   => __( 'Title', 'acs-courier-for-woocommerce' ),
				'type'    => 'text',
				'default' => __( 'ACS Courier', 'acs-courier-for-woocommerce' ),
			),
			'offer_locker' => array(
				'title'       => __( 'Offer pickup points', 'acs-courier-for-woocommerce' ),
				'type'        => 'checkbox',
				'default'     => 'yes',
				'description' => __( 'Let customers collect from an ACS store or Smartpoint locker.', 'acs-courier-for-woocommerce' ),
			),
			'bands'        => array(
				'title'       => __( 'Rate bands', 'acs-courier-for-woocommerce' ),
				'type'        => 'textarea',
				'default'     => "2|3.50|2.50\n5|5.00|3.80\n10|8.00|6.00",
				'description' => __( 'One band per line: max kg | home price | pickup point price. Used for Cyprus, and as a fallback if ACS pricing is unavailable.', 'acs-courier-for-woocommerce' ),
			),
			'per_extra_kg' => array(
				'title'   => __( 'Price per extra kg', 'acs-courier-for-woocommerce' ),
				'type'    => 'text',
				'default' => '1.20',
			),
			'free_from'    => array(
				'title'       => __( 'Free shipping from', 'acs-courier-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '0',
				'description' => __( 'Order total at or above which shipping is free. Zero disables it.', 'acs-courier-for-woocommerce' ),
			),
		);
	}

	/**
	 * Adds the ACS rates to the cart.
	 *
	 * @param array<string,mixed> $package Shipping package.
	 * @return void
	 */
	public function calculate_shipping( $package = array() ): void {
		$destination = $package['destination'] ?? array();
		$code        = isset( $destination['country'] ) ? (string) $destination['country'] : '';

		if ( ! Country::isSupported( $code ) ) {
			return;
		}

		$country  = Country::fromCode( $code );
		$weight   = Weight::fromKilograms( $this->packageWeightKg( $package ) );
		$resolver = $this->resolver();
		$total    = (float) WC()->cart->get_displayed_subtotal();
		$station  = '';

		$home = $resolver->resolve( $country, $weight, $station, false, $total );
		if ( null !== $home ) {
			$this->add_rate(
				array(
					'id'      => $this->id . ':home',
					'label'   => $this->title,
					'cost'    => $home,
					'package' => $package,
				)
			);
		}

		if ( 'yes' !== $this->get_option( 'offer_locker', 'yes' ) ) {
			return;
		}

		$locker = $resolver->resolve( $country, $weight, $station, true, $total );
		if ( null !== $locker ) {
			$this->add_rate(
				array(
					'id'      => $this->id . ':locker',
					'label'   => $this->title . ' — ' . __( 'Pickup point', 'acs-courier-for-woocommerce' ),
					'cost'    => $locker,
					'package' => $package,
				)
			);
		}
	}

	/**
	 * Builds the rate resolver from settings.
	 *
	 * @return RateResolver
	 */
	private function resolver(): RateResolver {
		$settings = Settings::all();

		return new RateResolver(
			new RetryingClient(
				new AcsClient(
					new WpHttpTransport(),
					SettingsResolver::resolve( $settings, SettingsResolver::definedConstants() )
				)
			),
			new RateTable(
				$this->parseBands( (string) $this->get_option( 'bands', '' ) ),
				(float) $this->get_option( 'per_extra_kg', '0' ),
				(float) $this->get_option( 'free_from', '0' )
			),
			(string) ( $settings['origin_station'] ?? '' ),
			(string) ( $settings['billing_code'] ?? '' )
		);
	}

	/**
	 * Parses the rate band textarea.
	 *
	 * @param string $raw One band per line: max kg | home | locker.
	 * @return list<array{max_kg:float,home:float,locker:float}>
	 */
	private function parseBands( string $raw ): array {
		$bands = array();

		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		if ( false === $lines ) {
			return array();
		}

		foreach ( $lines as $line ) {
			$parts = array_map( 'trim', explode( '|', (string) $line ) );
			if ( count( $parts ) < 3 || '' === $parts[0] ) {
				continue;
			}
			$bands[] = array(
				'max_kg' => (float) $parts[0],
				'home'   => (float) $parts[1],
				'locker' => (float) $parts[2],
			);
		}

		return $bands;
	}

	/**
	 * Total weight of a package in kilograms.
	 *
	 * @param array<string,mixed> $package Shipping package.
	 * @return float
	 */
	private function packageWeightKg( array $package ): float {
		$unit   = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$to_kg  = array(
			'kg'  => 1.0,
			'g'   => 0.001,
			'lbs' => 0.45359237,
			'oz'  => 0.028349523125,
		);
		$factor = $to_kg[ strtolower( $unit ) ] ?? 1.0;
		$total  = 0.0;

		foreach ( (array) ( $package['contents'] ?? array() ) as $item ) {
			$product = $item['data'] ?? null;
			if ( $product instanceof \WC_Product && '' !== (string) $product->get_weight() ) {
				$total += (float) $product->get_weight() * (int) ( $item['quantity'] ?? 1 );
			}
		}

		return $total * $factor;
	}
}

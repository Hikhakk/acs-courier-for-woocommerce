<?php
/**
 * Stores and queries ACS pickup points.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\RetryingClient;
use AcsCourier\Domain\PickupPoint;
use AcsCourier\Support\Installer;

/**
 * Stores and queries ACS pickup points.
 *
 * Checkout reads from the local table and never calls ACS, so a customer never
 * waits on a third party to choose a locker.
 */
final class PickupPointRepository {

	/**
	 * ACS method listing stores and Smartpoints.
	 */
	public const ALIAS_STATIONS = 'ACS_Stations';

	/**
	 * Shop kinds worth offering: central stores and lockers.
	 */
	public const KINDS = array( 1, 8 );

	/**
	 * Client used to reach ACS.
	 *
	 * @var RetryingClient
	 */
	private RetryingClient $client;

	/**
	 * Stores the client.
	 *
	 * @param RetryingClient $client Client used to reach ACS.
	 */
	public function __construct( RetryingClient $client ) {
		$this->client = $client;
	}

	/**
	 * Refreshes the cached points for a country.
	 *
	 * @param string $country ISO country code, GR or CY.
	 * @return int Number of points stored.
	 */
	public function sync( string $country ): int {
		global $wpdb;

		$table = Installer::tableName();
		$now   = gmdate( 'Y-m-d H:i:s' );
		$count = 0;

		foreach ( self::KINDS as $kind ) {
			$response = $this->client->call(
				self::ALIAS_STATIONS,
				array(
					'language'            => 'EN',
					'ACS_SHOP_COUNTRY_ID' => $country,
					'ACS_SHOP_KIND'       => $kind,
				)
			);

			$rows = $response['ACSTableOutput']['Table_Data'] ?? array();
			if ( ! is_array( $rows ) ) {
				continue;
			}

			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				try {
					$point = PickupPoint::fromAcsRow( $row, $country );
				} catch ( \InvalidArgumentException $e ) {
					// A malformed row must not abort the whole sync.
					continue;
				}

				$written = $wpdb->replace( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Custom table, no core API exists.
					$table,
					array(
						'point_id'   => $point->id(),
						'station_id' => $point->stationId(),
						'branch_id'  => $point->branchId(),
						'country'    => $point->country(),
						'kind'       => $point->isLocker() ? 8 : 1,
						'name'       => $point->name(),
						'address'    => $point->address(),
						'postcode'   => $point->postcode(),
						'hours'      => $point->hours(),
						'lat'        => $point->lat(),
						'lng'        => $point->lng(),
						'updated_at' => $now,
					)
				);

				// Count what was actually stored, not what was attempted.
				if ( false !== $written ) {
					++$count;
				}
			}//end foreach
		}//end foreach

		return $count;
	}

	/**
	 * Finds points near a postcode, nearest first.
	 *
	 * @param string $country  ISO country code.
	 * @param float  $lat      Latitude to measure from.
	 * @param float  $lng      Longitude to measure from.
	 * @param int    $limit    Maximum points to return.
	 * @param bool   $lockers  Whether to restrict to lockers.
	 * @return list<array<string,mixed>>
	 */
	public function nearest( string $country, float $lat, float $lng, int $limit = 20, bool $lockers = false ): array {
		global $wpdb;

		$table = Installer::tableName();

		// Distance is computed in SQL so only the closest rows travel to PHP.
		$sql = "SELECT point_id, station_id, branch_id, name, address, postcode, hours, kind,
				( 6371 * ACOS(
					LEAST( 1.0, COS( RADIANS(%f) ) * COS( RADIANS(lat) )
					* COS( RADIANS(lng) - RADIANS(%f) )
					+ SIN( RADIANS(%f) ) * SIN( RADIANS(lat) ) )
				) ) AS distance_km
			FROM {$table}
			WHERE country = %s AND lat IS NOT NULL";

		$args = array( $lat, $lng, $lat, $country );

		if ( $lockers ) {
			$sql   .= ' AND kind = %d';
			$args[] = 8;
		}

		$sql   .= ' ORDER BY distance_km ASC LIMIT %d';
		$args[] = $limit;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared below; custom table.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Counts stored points for a country.
	 *
	 * @param string $country ISO country code.
	 * @return int
	 */
	public function count( string $country ): int {
		global $wpdb;
		$table = Installer::tableName();

		// A table name cannot be a placeholder; it comes from $wpdb->prefix, which is trusted.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$sql = "SELECT COUNT(*) FROM {$table} WHERE country = %s";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $country ) );
	}
}

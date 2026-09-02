<?php
/**
 * An ACS store or Smartpoint locker a customer can collect from.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

/**
 * An ACS store or Smartpoint locker a customer can collect from.
 */
final class PickupPoint {

	/**
	 * ACS_SHOP_KIND for a Smartpoint with a parcel locker.
	 */
	public const KIND_LOCKER = 8;

	/**
	 * Mean radius of the Earth in kilometres.
	 */
	private const EARTH_RADIUS_KM = 6371.0;

	/**
	 * ACS station code.
	 *
	 * @var string
	 */
	private string $station_id;

	/**
	 * ACS branch code within the station.
	 *
	 * @var int
	 */
	private int $branch_id;

	/**
	 * Human-readable name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Street address.
	 *
	 * @var string
	 */
	private string $address;

	/**
	 * Postcode.
	 *
	 * @var string
	 */
	private string $postcode;

	/**
	 * ISO country code.
	 *
	 * @var string
	 */
	private string $country;

	/**
	 * ACS shop kind.
	 *
	 * @var int
	 */
	private int $kind;

	/**
	 * Opening hours as ACS words them.
	 *
	 * @var string
	 */
	private string $hours;

	/**
	 * Latitude, or null when ACS has none.
	 *
	 * @var float|null
	 */
	private ?float $lat;

	/**
	 * Longitude, or null when ACS has none.
	 *
	 * @var float|null
	 */
	private ?float $lng;

	/**
	 * Builds a pickup point.
	 *
	 * @param string     $station_id ACS station code.
	 * @param int        $branch_id  ACS branch code.
	 * @param string     $name       Human-readable name.
	 * @param string     $address    Street address.
	 * @param string     $postcode   Postcode.
	 * @param string     $country    ISO country code.
	 * @param int        $kind       ACS shop kind.
	 * @param string     $hours      Opening hours.
	 * @param float|null $lat        Latitude.
	 * @param float|null $lng        Longitude.
	 */
	public function __construct(
		string $station_id,
		int $branch_id,
		string $name,
		string $address,
		string $postcode,
		string $country,
		int $kind,
		string $hours = '',
		?float $lat = null,
		?float $lng = null
	) {
		$this->station_id = $station_id;
		$this->branch_id  = $branch_id;
		$this->name       = $name;
		$this->address    = $address;
		$this->postcode   = $postcode;
		$this->country    = $country;
		$this->kind       = $kind;
		$this->hours      = $hours;
		$this->lat        = $lat;
		$this->lng        = $lng;
	}

	/**
	 * Builds a point from an ACS_Stations row.
	 *
	 * @param array<string,mixed> $row Row as ACS returned it.
	 * @return self
	 * @throws \InvalidArgumentException If the row has no station code.
	 */
	public static function fromAcsRow( array $row ): self {
		$station = isset( $row['ACS_SHOP_STATION_ID'] ) ? trim( (string) $row['ACS_SHOP_STATION_ID'] ) : '';
		if ( '' === $station ) {
			throw new \InvalidArgumentException( 'An ACS pickup point row must carry a station code.' );
		}

		$number = static function ( $value ): ?float {
			if ( null === $value || '' === trim( (string) $value ) ) {
				return null;
			}
			return (float) $value;
		};

		return new self(
			$station,
			isset( $row['ACS_SHOP_BRANCH_ID'] ) ? (int) $row['ACS_SHOP_BRANCH_ID'] : 0,
			isset( $row['ACS_SHOP_STATION_DESCR'] ) ? trim( (string) $row['ACS_SHOP_STATION_DESCR'] ) : '',
			isset( $row['ACS_SHOP_ADDRESS'] ) ? trim( (string) $row['ACS_SHOP_ADDRESS'] ) : '',
			isset( $row['ACS_SHOP_ZIPCODE'] ) ? trim( (string) $row['ACS_SHOP_ZIPCODE'] ) : '',
			isset( $row['ACS_SHOP_COUNTRY_ID'] ) ? strtoupper( trim( (string) $row['ACS_SHOP_COUNTRY_ID'] ) ) : '',
			isset( $row['ACS_SHOP_KIND'] ) ? (int) $row['ACS_SHOP_KIND'] : 0,
			isset( $row['ACS_SHOP_WORKING_HOURS'] ) ? trim( (string) $row['ACS_SHOP_WORKING_HOURS'] ) : '',
			$number( $row['ACS_SHOP_LAT'] ?? null ),
			$number( $row['ACS_SHOP_LONG'] ?? null )
		);
	}

	/**
	 * A stable identifier combining station and branch.
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->station_id . ':' . $this->branch_id;
	}

	/**
	 * ACS station code.
	 *
	 * @return string
	 */
	public function stationId(): string {
		return $this->station_id;
	}

	/**
	 * ACS branch code.
	 *
	 * @return int
	 */
	public function branchId(): int {
		return $this->branch_id;
	}

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Street address.
	 *
	 * @return string
	 */
	public function address(): string {
		return $this->address;
	}

	/**
	 * Postcode.
	 *
	 * @return string
	 */
	public function postcode(): string {
		return $this->postcode;
	}

	/**
	 * ISO country code.
	 *
	 * @return string
	 */
	public function country(): string {
		return $this->country;
	}

	/**
	 * Opening hours.
	 *
	 * @return string
	 */
	public function hours(): string {
		return $this->hours;
	}

	/**
	 * Whether this is a Smartpoint with a parcel locker.
	 *
	 * @return bool
	 */
	public function isLocker(): bool {
		return self::KIND_LOCKER === $this->kind;
	}

	/**
	 * Latitude, or null when ACS gave none.
	 *
	 * @return float|null
	 */
	public function lat(): ?float {
		return $this->lat;
	}

	/**
	 * Longitude, or null when ACS gave none.
	 *
	 * @return float|null
	 */
	public function lng(): ?float {
		return $this->lng;
	}

	/**
	 * Whether ACS supplied coordinates for this point.
	 *
	 * @return bool
	 */
	public function hasCoordinates(): bool {
		return null !== $this->lat && null !== $this->lng;
	}

	/**
	 * Great-circle distance to a coordinate, in kilometres.
	 *
	 * @param float $lat Latitude to measure from.
	 * @param float $lng Longitude to measure from.
	 * @return float|null Null when ACS gave no coordinates for this point.
	 */
	public function distanceKm( float $lat, float $lng ): ?float {
		if ( null === $this->lat || null === $this->lng ) {
			return null;
		}

		$d_lat = deg2rad( $this->lat - $lat );
		$d_lng = deg2rad( $this->lng - $lng );

		$a = sin( $d_lat / 2 ) ** 2
			+ cos( deg2rad( $lat ) ) * cos( deg2rad( $this->lat ) ) * sin( $d_lng / 2 ) ** 2;

		return self::EARTH_RADIUS_KM * 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
	}
}

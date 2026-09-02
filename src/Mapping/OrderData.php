<?php
/**
 * The parts of a WooCommerce order this plugin needs, as plain data.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

/**
 * The parts of a WooCommerce order this plugin needs, as plain data.
 */
final class OrderData {

	/**
	 * Id.
	 *
	 * @var int
	 */
	public int $id = 0;
	/**
	 * Name.
	 *
	 * @var string
	 */
	public string $name = '';
	/**
	 * Company.
	 *
	 * @var string
	 */
	public string $company = '';
	/**
	 * Address1.
	 *
	 * @var string
	 */
	public string $address1 = '';
	/**
	 * Address2.
	 *
	 * @var string
	 */
	public string $address2 = '';
	/**
	 * City.
	 *
	 * @var string
	 */
	public string $city = '';
	/**
	 * Postcode.
	 *
	 * @var string
	 */
	public string $postcode = '';
	/**
	 * Country code.
	 *
	 * @var string
	 */
	public string $countryCode = '';
	/**
	 * Phone.
	 *
	 * @var string
	 */
	public string $phone = '';
	/**
	 * Email.
	 *
	 * @var string
	 */
	public string $email = '';
	/**
	 * Weight.
	 *
	 * @var float
	 */
	public float $weight = 0.0;
	/**
	 * Weight unit.
	 *
	 * @var string
	 */
	public string $weightUnit = 'kg';

	/**
	 * Units ordered, recorded for later use. NOT the parcel count.
	 *
	 * @var int
	 */
	public int $itemCount = 1;

	/**
	 * Customer note.
	 *
	 * @var string
	 */
	public string $customerNote = '';
}

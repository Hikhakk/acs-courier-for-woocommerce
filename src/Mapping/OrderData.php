<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

final class OrderData {

	public int $id             = 0;
	public string $name        = '';
	public string $company     = '';
	public string $address1    = '';
	public string $address2    = '';
	public string $city        = '';
	public string $postcode    = '';
	public string $countryCode = '';
	public string $phone       = '';
	public string $email       = '';
	public float $weight       = 0.0;
	public string $weightUnit  = 'kg';

	/** Units ordered, recorded for later use. NOT the parcel count. */
	public int $itemCount = 1;

	public string $customerNote = '';
}

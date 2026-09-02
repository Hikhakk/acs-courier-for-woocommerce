<?php
/**
 * Merchant configuration applied to every shipment.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

/**
 * Merchant configuration applied to every shipment.
 */
final class MapperSettings {

	/**
	 * Sender.
	 *
	 * @var string
	 */
	public string $sender = '';
	/**
	 * Billing code.
	 *
	 * @var string
	 */
	public string $billingCode = '';
	/**
	 * Charge type.
	 *
	 * @var int
	 */
	public int $chargeType = 2;
	/**
	 * Default content type id.
	 *
	 * @var int|null
	 */
	public ?int $defaultContentTypeId = null;
	/**
	 * Language.
	 *
	 * @var string
	 */
	public string $language = 'EN';
	/**
	 * Pickup date.
	 *
	 * @var string
	 */
	public string $pickupDate = '';
}

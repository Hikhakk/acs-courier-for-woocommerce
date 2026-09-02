<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Mapping;

final class MapperSettings {

	public string $sender             = '';
	public string $billingCode        = '';
	public int $chargeType            = 2;
	public ?int $defaultContentTypeId = null;
	public string $language           = 'EN';
	public string $pickupDate         = '';
}

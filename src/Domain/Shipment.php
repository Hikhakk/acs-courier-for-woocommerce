<?php
/**
 * A shipment as the plugin understands it, before ACS field naming is applied.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

/**
 * A shipment in the plugin's own terms, before ACS field naming is applied.
 */
final class Shipment {

	/**
	 * Recipient name.
	 *
	 * @var string
	 */
	public string $recipientName = '';
	/**
	 * Recipient address.
	 *
	 * @var string
	 */
	public string $recipientAddress = '';
	/**
	 * Recipient address number.
	 *
	 * @var string
	 */
	public string $recipientAddressNumber = '';
	/**
	 * Recipient zip.
	 *
	 * @var string
	 */
	public string $recipientZip = '';
	/**
	 * Recipient region.
	 *
	 * @var string
	 */
	public string $recipientRegion = '';
	/**
	 * Recipient phone.
	 *
	 * @var string
	 */
	public string $recipientPhone = '';
	/**
	 * Recipient cell phone.
	 *
	 * @var string
	 */
	public string $recipientCellPhone = '';
	/**
	 * Recipient email.
	 *
	 * @var string
	 */
	public string $recipientEmail = '';
	/**
	 * Recipient company.
	 *
	 * @var string
	 */
	public string $recipientCompany = '';

	/**
	 * Country.
	 *
	 * @var Country|null
	 */
	public ?Country $country = null;
	/**
	 * Weight.
	 *
	 * @var Weight|null
	 */
	public ?Weight $weight = null;

	/**
	 * Item quantity.
	 *
	 * @var int
	 */
	public int $itemQuantity = 1;
	/**
	 * Pickup date.
	 *
	 * @var string
	 */
	public string $pickupDate = '';
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
	 * Who pays: 2 = sender, 4 = recipient.
	 *
	 * @var int
	 */
	public int $chargeType = 2;

	/**
	 * ACS product codes, e.g. REC, SAT, COD.
	 *
	 * @var list<string>
	 */
	public array $deliveryProducts = array();

	/**
	 * Content type id.
	 *
	 * @var int|null
	 */
	public ?int $contentTypeId = null;

	/**
	 * Station destination.
	 *
	 * @var string|null
	 */
	public ?string $stationDestination = null;
	/**
	 * Station branch destination.
	 *
	 * @var int|null
	 */
	public ?int $stationBranchDestination = null;

	/**
	 * Cod amount.
	 *
	 * @var float|null
	 */
	public ?float $codAmount = null;
	/**
	 * Cod payment way.
	 *
	 * @var int|null
	 */
	public ?int $codPaymentWay = null;
	/**
	 * Insurance amount.
	 *
	 * @var float|null
	 */
	public ?float $insuranceAmount = null;

	/**
	 * Length cm.
	 *
	 * @var float|null
	 */
	public ?float $lengthCm = null;
	/**
	 * Width cm.
	 *
	 * @var float|null
	 */
	public ?float $widthCm = null;
	/**
	 * Height cm.
	 *
	 * @var float|null
	 */
	public ?float $heightCm = null;

	/**
	 * Delivery notes.
	 *
	 * @var string
	 */
	public string $deliveryNotes = '';
	/**
	 * Reference key1.
	 *
	 * @var string
	 */
	public string $referenceKey1 = '';
	/**
	 * Reference key2.
	 *
	 * @var string
	 */
	public string $referenceKey2 = '';
	/**
	 * Language.
	 *
	 * @var string
	 */
	public string $language = 'EN';

	/**
	 * Is to pickup point.
	 *
	 * @return bool
	 */
	public function isToPickupPoint(): bool {
		return null !== $this->stationDestination && null !== $this->stationBranchDestination;
	}
}

<?php
/**
 * A shipment as the plugin understands it, before ACS field naming is applied.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

final class Shipment
{
    public string $recipientName = '';
    public string $recipientAddress = '';
    public string $recipientAddressNumber = '';
    public string $recipientZip = '';
    public string $recipientRegion = '';
    public string $recipientPhone = '';
    public string $recipientCellPhone = '';
    public string $recipientEmail = '';
    public string $recipientCompany = '';

    public ?Country $country = null;
    public ?Weight $weight = null;

    public int $itemQuantity = 1;
    public string $pickupDate = '';
    public string $sender = '';
    public string $billingCode = '';

    /** 2 = charge sender, 4 = charge recipient. */
    public int $chargeType = 2;

    /** @var list<string> ACS product codes, e.g. REC, SAT, COD. */
    public array $deliveryProducts = [];

    public ?int $contentTypeId = null;

    public ?string $stationDestination = null;
    public ?int $stationBranchDestination = null;

    public ?float $codAmount = null;
    public ?int $codPaymentWay = null;
    public ?float $insuranceAmount = null;

    public ?float $lengthCm = null;
    public ?float $widthCm = null;
    public ?float $heightCm = null;

    public string $deliveryNotes = '';
    public string $referenceKey1 = '';
    public string $referenceKey2 = '';
    public string $language = 'EN';

    public function isToPickupPoint(): bool
    {
        return null !== $this->stationDestination && null !== $this->stationBranchDestination;
    }
}

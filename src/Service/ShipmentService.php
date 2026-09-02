<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Service;

use AcsCourier\Api\AcsException;
use AcsCourier\Api\RetryingClient;
use AcsCourier\Domain\Shipment;
use AcsCourier\Mapping\FieldMap;

final class ShipmentService
{
    public const ALIAS_CREATE = 'ACS_Create_Voucher';

    private RetryingClient $client;

    public function __construct(RetryingClient $client)
    {
        $this->client = $client;
    }

    /**
     * @throws \InvalidArgumentException When the shipment is locally invalid.
     * @throws AcsException When ACS rejects it.
     */
    public function create(Shipment $shipment): string
    {
        $problems = FieldMap::validate($shipment);
        if ([] !== $problems) {
            throw new \InvalidArgumentException(implode(' ', $problems));
        }

        $response = $this->client->call(self::ALIAS_CREATE, FieldMap::toCreateVoucherParams($shipment));

        $values  = $response['ACSValueOutput'] ?? [];
        $voucher = is_array($values) && isset($values[0]['Voucher_No'])
            ? trim((string) $values[0]['Voucher_No'])
            : '';

        if ('' === $voucher) {
            throw AcsException::business(
                'ACS accepted the request but returned no voucher number.',
                self::ALIAS_CREATE
            );
        }

        return $voucher;
    }
}

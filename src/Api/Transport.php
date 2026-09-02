<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

interface Transport
{
    /**
     * @param array<string,mixed>  $payload
     * @param array<string,string> $headers
     * @throws TransportFailure On network-level failure.
     */
    public function post(string $url, array $payload, array $headers): TransportResponse;
}

<?php
/**
 * Test double: replays queued responses and records requests.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class ArrayTransport implements Transport
{
    /** @var list<TransportResponse> */
    private array $queue;

    /** @var list<array{url:string,payload:array<string,mixed>,headers:array<string,string>}> */
    private array $requests = [];

    /** @param list<TransportResponse> $queue */
    public function __construct(array $queue)
    {
        $this->queue = $queue;
    }

    public function post(string $url, array $payload, array $headers): TransportResponse
    {
        $this->requests[] = ['url' => $url, 'payload' => $payload, 'headers' => $headers];

        if ($this->queue === []) {
            throw new \RuntimeException('ArrayTransport queue exhausted.');
        }

        return array_shift($this->queue);
    }

    /** @return list<array{url:string,payload:array<string,mixed>,headers:array<string,string>}> */
    public function requests(): array
    {
        return $this->requests;
    }
}

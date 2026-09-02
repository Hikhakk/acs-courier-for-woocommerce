<?php
/**
 * Decorates AcsClient with bounded exponential backoff for transient failures.
 *
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Api;

final class RetryingClient
{
    private AcsClient $inner;
    private int $maxAttempts;
    /** @var callable */
    private $sleeper;

    public function __construct(AcsClient $inner, int $maxAttempts = 3, ?callable $sleeper = null)
    {
        $this->inner       = $inner;
        $this->maxAttempts = max(1, $maxAttempts);
        $this->sleeper     = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1000000));
        };
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     * @throws AcsException
     */
    public function call(string $alias, array $params = []): array
    {
        $attempt = 0;

        while (true) {
            ++$attempt;
            try {
                return $this->inner->call($alias, $params);
            } catch (AcsException $e) {
                if (!$e->isRetryable() || $attempt >= $this->maxAttempts) {
                    throw $e;
                }
                ($this->sleeper)($this->backoffSeconds($attempt));
            }
        }
    }

    private function backoffSeconds(int $attempt): float
    {
        // 0.5s, 1s, 2s ... capped at 8s.
        return min(8.0, 0.5 * (2 ** ($attempt - 1)));
    }
}

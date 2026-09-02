<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

final class Country
{
    public const GR = 'GR';
    public const CY = 'CY';

    private string $code;

    private function __construct(string $code)
    {
        $this->code = $code;
    }

    public static function greece(): self
    {
        return new self(self::GR);
    }

    public static function cyprus(): self
    {
        return new self(self::CY);
    }

    public static function fromCode(string $code): self
    {
        $normalised = strtoupper(trim($code));

        if (self::GR === $normalised) {
            return self::greece();
        }
        if (self::CY === $normalised) {
            return self::cyprus();
        }

        throw new \InvalidArgumentException(
            'ACS supports voucher creation for GR and CY only; received "' . $code . '".'
        );
    }

    public static function isSupported(string $code): bool
    {
        return in_array(strtoupper(trim($code)), [self::GR, self::CY], true);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function isCyprus(): bool
    {
        return self::CY === $this->code;
    }

    public function isGreece(): bool
    {
        return self::GR === $this->code;
    }

    public function zipLength(): int
    {
        return $this->isCyprus() ? 4 : 5;
    }

    public function isValidZip(string $zip): bool
    {
        return 1 === preg_match('/^\d{' . $this->zipLength() . '}$/', trim($zip));
    }

    public function requiresContentType(): bool
    {
        return $this->isCyprus();
    }

    public function supportsLivePricing(): bool
    {
        return $this->isGreece();
    }
}

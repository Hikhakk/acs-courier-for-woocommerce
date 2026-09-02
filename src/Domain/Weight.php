<?php
/**
 * @package AcsCourier
 * @license GPL-2.0-or-later
 */

declare(strict_types=1);

namespace AcsCourier\Domain;

final class Weight
{
    public const MIN_KG              = 0.5;
    public const MAX_KG              = 999.0;
    public const VOLUMETRIC_DIVISOR  = 5000;

    private float $kilograms;

    private function __construct(float $kilograms)
    {
        $this->kilograms = $kilograms;
    }

    public static function fromKilograms(float $kg): self
    {
        return new self(max(0.0, $kg));
    }

    public static function volumetric(float $lengthCm, float $widthCm, float $heightCm): self
    {
        return new self(($lengthCm * $widthCm * $heightCm) / self::VOLUMETRIC_DIVISOR);
    }

    public function kilograms(): float
    {
        return $this->kilograms;
    }

    public function isAboveMaximum(): bool
    {
        return $this->kilograms > self::MAX_KG;
    }

    /** Clamped and rounded exactly as ACS expects it on the wire. */
    public function forAcs(): float
    {
        $clamped = min(self::MAX_KG, max(self::MIN_KG, $this->kilograms));
        return round($clamped, 2);
    }

    public function isHeavierThan(self $other): bool
    {
        return $this->kilograms > $other->kilograms;
    }
}

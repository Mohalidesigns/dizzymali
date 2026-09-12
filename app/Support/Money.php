<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of money in integer minor units (kobo for NGN).
 *
 * There is no float anywhere in this class and there must never be one. Naira
 * amounts in this business routinely run to eight figures in kobo; a float
 * multiplication of 15_000_00 by 4.5 will eventually produce a customer-visible
 * rounding error, and the customer will be right and we will be wrong.
 *
 * @immutable
 */
final class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public readonly int $minor,
        public readonly string $currency,
    ) {}

    public static function ofMinor(int $minor, string $currency = 'NGN'): self
    {
        return new self($minor, strtoupper($currency));
    }

    public static function zero(string $currency = 'NGN'): self
    {
        return new self(0, strtoupper($currency));
    }

    /**
     * Only for admin input and seeders, where a whole-naira figure is typed by a
     * human. Never call this with a computed value.
     */
    public static function ofMajorUnits(int $major, string $currency = 'NGN'): self
    {
        return new self($major * 100, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    public function times(int $multiplier): self
    {
        return new self($this->minor * $multiplier, $this->currency);
    }

    /**
     * Multiply by a quantity expressed in hundredths — the representation used for
     * yardage, which is DECIMAL(5,2) in the database.
     *
     * 4.5 yards is passed as 450. Rounding is half-up on the final kobo only, so
     * 450 hundredths of 1_500_000 kobo is exactly 6_750_000 kobo with no drift.
     */
    public function timesHundredths(int $hundredths): self
    {
        if ($hundredths < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        $product = $this->minor * $hundredths;
        $result = intdiv($product, 100);

        if ($product % 100 >= 50) {
            $result++;
        }

        return new self($result, $this->currency);
    }

    /** Add a percentage expressed in basis points (850 = 8.50%). */
    public function plusBasisPoints(int $basisPoints): self
    {
        $product = $this->minor * $basisPoints;
        $uplift = intdiv($product, 10_000);

        if ($product % 10_000 >= 5_000) {
            $uplift++;
        }

        return new self($this->minor + $uplift, $this->currency);
    }

    /** Take a percentage expressed in basis points (6_000 = 60%), rounded half-up. */
    public function percentageBasisPoints(int $basisPoints): self
    {
        $product = $this->minor * $basisPoints;
        $result = intdiv($product, 10_000);

        if ($product % 10_000 >= 5_000) {
            $result++;
        }

        return new self($result, $this->currency);
    }

    /** Round the amount UP to the nearest $step minor units. Never rounds down. */
    public function roundUpToNearest(int $step): self
    {
        if ($step <= 1) {
            return $this;
        }

        $remainder = $this->minor % $step;

        if ($remainder === 0) {
            return $this;
        }

        return new self($this->minor + ($step - $remainder), $this->currency);
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor && $this->currency === $other->currency;
    }

    public function greaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->minor > $other->minor;
    }

    /** Clamp at zero — used where a discount must not push a total negative. */
    public function atLeastZero(): self
    {
        return $this->minor < 0 ? new self(0, $this->currency) : $this;
    }

    /**
     * Convert to another currency at a rate expressed in units of the target
     * currency per 1 major unit of this currency, given in 1e8 fixed point.
     *
     * GBP at 0.00051200 per NGN is passed as 51_200.
     */
    public function convertTo(string $currency, int $rate1e8, int $targetDecimals = 2): self
    {
        if ($rate1e8 < 0) {
            throw new InvalidArgumentException('FX rate cannot be negative.');
        }

        // minor(base) -> major(base) -> major(target) -> minor(target), all integer.
        $scale = 10 ** $targetDecimals;
        $product = $this->minor * $rate1e8 * $scale;
        $divisor = 100 * 100_000_000;

        $result = intdiv($product, $divisor);

        if ($product % $divisor >= intdiv($divisor, 2)) {
            $result++;
        }

        return new self($result, strtoupper($currency));
    }

    public function format(int $decimals = 2, string $symbol = '₦'): string
    {
        $negative = $this->minor < 0;
        $abs = abs($this->minor);
        $divisor = 10 ** $decimals;
        $major = intdiv($abs, $divisor);
        $minorPart = $abs % $divisor;

        $formatted = number_format($major).($decimals > 0
            ? '.'.str_pad((string) $minorPart, $decimals, '0', STR_PAD_LEFT)
            : '');

        return ($negative ? '-' : '').$symbol.$formatted;
    }

    /** @return array{minor:int,currency:string} */
    public function jsonSerialize(): array
    {
        return ['minor' => $this->minor, 'currency' => $this->currency];
    }

    public function __toString(): string
    {
        return $this->minor.' '.$this->currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}.",
            );
        }
    }
}

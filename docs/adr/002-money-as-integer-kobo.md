# ADR 002 — Money is an integer-kobo value object, not brick/money and not a float

**Status:** Accepted · 12 September 2026

## Context

`CLAUDE.md` rule 1 is absolute: money is integer kobo, no floats anywhere. The question was
only how to enforce it. The plan suggested `brick/money`.

The arithmetic this application actually does is narrow: multiply a per-yard price by a
yardage expressed to two decimal places, add a percentage surcharge, convert to a display
currency at a fixed rate, round the display figure up to the nearest minor unit.

## Decision

A small `App\Support\Money` final value object holding `int $minor` and `string $currency`.

The operations it exposes are exactly the ones the business needs, and each is written so no
float can enter:

- `timesHundredths(int)` — yardage is `DECIMAL(5,2)`, so 4.5 yards arrives as `450` and the
  product is an integer divided by 100 with an explicit half-up step. `Money::ofMinor(15_000_00)->timesHundredths(450)`
  is exactly `6_750_000` kobo, every time.
- `plusBasisPoints(int)` — a 25% express surcharge is `2_500` bp, an 8% FX margin is `800` bp.
- `convertTo(string, int $rate1e8, int $decimals)` — the FX rate is 1e8 fixed point, so
  0.000512 GBP per NGN is `51_200` and the whole conversion is integer.
- `roundUpToNearest(int)` — display amounts round up, never down.

`brick/money` would have done this correctly too. It was rejected for one reason: it is a
general-purpose library with a large surface, and a developer reaching for the wrong method
on it — `Money::of(4.5)` — reintroduces exactly the bug the rule exists to prevent. A class
with ten methods, none of which accept a float, cannot be misused that way.

## Consequences

- Every monetary column is `BIGINT` named `*_kobo`, and reading one always produces an `int`.
- `Money::ofMajorUnits()` exists for admin input and seeders, where a human types whole naira.
  It is documented as never to be called with a computed value.
- Mixing currencies throws rather than silently adding pence to kobo.
- The whole class is covered by unit tests with no framework loaded, including the cases
  where a naive float implementation drifts.

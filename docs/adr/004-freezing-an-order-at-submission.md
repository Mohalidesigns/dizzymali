# ADR 004 — An order freezes completely the moment it leaves draft

**Status:** Accepted · 12 September 2026

## Context

`CLAUDE.md` rule 5 requires that historic orders are immutable: measurements, item prices
and the FX rate are snapshotted at submission, and editing a saved profile must never alter
a garment already cut.

The first implementation defined "locked" as everything past `quote_accepted`, leaving
`submitted` and `quote_accepted` open for edits. A feature test caught the consequence
immediately: raising the per-yard price of a fabric in the admin panel repriced an order
that had already been submitted, from ₦162,000 to ₦282,000, without anybody touching it.

That is not a hypothetical. Fabric prices move, and a customer who was quoted a figure and
then sees a different one on their order page has been given a very good reason never to
order again.

## Decision

`OrderStatus::isLocked()` returns true for every state except `draft`.

Submission is the single freeze point, and it does four things in one transaction:

1. Recalculates the quote from the live catalogue, one last time.
2. Writes `measurement_snapshot`, `garment_type_snapshot` and `fabric_variant_snapshot` onto
   each line as frozen JSON — deliberately *not* foreign keys.
3. Snapshots the shipping address.
4. Freezes `fx_rate_used` and `fx_margin_percent`, so a customer quoted £180 pays £180 even
   if the naira moves overnight.

`RecalculateQuote` then reads snapshots rather than the catalogue for any order that is not a
draft. Running it against a submitted order is therefore idempotent, which is what makes it
safe to call from an admin screen.

## Consequences

- A customer cannot edit after submitting. Changes go through staff, which is how a workshop
  actually works — somebody needs to know before the cloth is cut.
- Price rises, fabric discontinuations and FX moves cannot reach backwards.
- Two feature tests hold this: one edits a saved measurement profile after submission and
  asserts the order's frozen chest measurement is unchanged; the other doubles both the
  sewing cost and the per-yard price and asserts the total does not move.
- Storage cost: a few kilobytes of JSON per order. Against the cost of one repriced order,
  this is not a trade-off worth discussing.

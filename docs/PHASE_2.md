# Phase 2 — what was built

## Payments

A gateway abstraction with three implementations behind one interface.

- **Paystack** — primary, complete and live-ready.
- **Flutterwave** — the international fallback, written in full against the v3 API. Inert
  until credentials arrive: reported as "awaiting credentials", skipped when choosing a
  gateway, and it throws rather than pretending to work if called directly.
- **Bank transfer** — works today with no gateway at all. Staff confirm receipt from the
  order screen, which routes through the same settlement path and so produces the same audit
  trail, state transition and notifications as a card payment.

Deposits (60/40 by default) are computed in integer kobo from the order total and never
exceed what is actually owed. Every attempt gets its own idempotency key.

**The trust boundary** — [ADR 006](adr/006-webhook-trust-boundary.md). Signature verified in
constant time; re-verified against the gateway's own API; amount and currency matched
exactly. A forged signature gets `401` and an empty body. A tampered amount marks the payment
failed and leaves the order untouched. All three are tested.

## Notifications

Mail, in-app and WhatsApp, every one queued. Which channels a notification uses is config,
not code, so you can turn one off without a deploy.

WhatsApp is **configured and on hold**: the Cloud API driver is complete, the log driver is
what runs, and the entire path — event, listener, channel, opt-in check, phone normalisation,
template parameters — executes and is tested today. Template names come from config because
Meta decides what they are called.

Consent is enforced at the channel: no number or no opt-in means no message.

Stage notifications fire on the five stages a customer sees, not the sixteen internal ones —
nobody wants four texts about one garment being cut.

## Media and placeholders

Upload → queued job → AVIF, WebP and JPEG at five widths. Re-encoding through GD is what
kills a polyglot payload; EXIF is discarded because it carries GPS.

Until the photography lands, every catalogue image falls back to a generated SVG built from
the record's own slug and the DizzyMali palette — deterministic, inline, and labelled so
staff can see what is missing. `Admin → Photography` is the list of what is still to shoot.

The contrast helper measures both options rather than using a brightness threshold, which is
what gets ink onto terracotta instead of the white that fails AA at 3.46:1.

## The rest

- **CMS** — seven block types, scheduled publish windows, drag-to-reorder. Nothing publishes
  while it still has an image without alt text.
- **Progress photos** — staff upload per stage from the order screen; customers see them on
  their timeline. Served through an authorisation check, never a public bucket. One
  notification per batch.
- **Shipping** — zones and weight-banded rates, carrier tracking with deep links for DHL,
  GIG, Aramex, FedEx and UPS. HS code and declared value on every shipment, because saying so
  upfront is what stops a parcel being refused at the door.
- **Currencies** — rates entered as naira-per-unit (how a person thinks about it), inverted
  once where it can be tested. `fx:refresh` behind a provider interface, `manual` by default.
- **SEO** — per-page meta and Open Graph, JSON-LD for garments, sitemap, robots.

## Gates

| Gate | Result |
|---|---|
| Pest | 129 tests, 289 assertions |
| Larastan level 6 | no errors |
| Pint | clean |
| `tsc --noEmit` | clean, no `any` |
| `npm run build` | largest page chunk 8.2 KB gzipped |

## The bug the tests caught this phase

A bank transfer recorded against an order at `quote_accepted` could not reach `paid` —
`quote_accepted → paid` is not a legal transition, and only card checkout passes through
`payment_pending` on its way. The money would have been in the bank with the order stuck.

Fixed in `SettlePayment` by stepping through `payment_pending` when needed, rather than
weakening the state machine.

## What is still not built

- Ready-to-wear catalogue, reviews, coupons, referrals, wishlist (Phase 3)
- Abandoned-draft recovery emails
- Tailor PWA for the workshop floor
- Reports beyond the dashboard
- Video transcoding to HLS for clips over 30 seconds

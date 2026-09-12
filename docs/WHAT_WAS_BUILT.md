# What was built — Phase 0 and Phase 1

Against `IMPLEMENTATION_PLAN.md` §16.

## Phase 0 — foundations

| | |
|---|---|
| Agent team wired up | `.claude/agents/` (14 agents), `CLAUDE.md` and `docs/` moved to the repo root |
| Auth | Register, login, logout, email verification, password reset — Inertia pages, rate-limited |
| Roles | `spatie/laravel-permission`; customer, tailor, staff, admin, super-admin with a permission matrix |
| Design tokens | Tailwind 4 `@theme` from the DizzyMali palette; Bodoni Moda, Jost, DM Sans, IBM Plex Sans |
| Component base | Button, Card, Field, Input, Badge, Swatch, EmptyState, Spinner, ErrorNote |
| Layouts | Storefront and admin shells |
| Security headers | Middleware: nosniff, frame-deny, referrer policy, permissions policy, HSTS on HTTPS |

## Phase 1 — the bespoke core

| | |
|---|---|
| Schema | 26 tables. Money `BIGINT` kobo throughout; measurements `DECIMAL(5,2)` inches |
| Catalogue | Garment types, yardage rules, fabrics, materials, variants, option groups and options |
| Measurements | 13 fields with help text and plausibility bounds; profiles with in/cm entry; staff review queue |
| Pricing engine | `QuoteCalculator` + `YardageCalculator` + `Money` — pure, deterministic, 100% unit-tested |
| Order wizard | Six steps with a live price panel and server-side draft autosave after every change |
| State machine | 16 internal states, 5 customer-facing; every transition validated and recorded |
| Admin | Dashboard, orders list and detail with stage advancement, fabrics, garments and yardage rules, measurement review, customers |
| Seeders | 4 garments, 4 materials, 15 variants, 13 measurement fields, options, currencies, 6 shipping zones, demo dataset |
| Tests | 66 tests, 176 assertions |

## Deliberate divergences from the plan

Each has an ADR.

| Divergence | ADR |
|---|---|
| Inertia + React in one app, not a headless API with two SPAs | [001](adr/001-inertia-over-two-spas.md) |
| A hand-rolled `Money` value object, not `brick/money` | [002](adr/002-money-as-integer-kobo.md) |
| A native enum state machine, not `spatie/laravel-model-states` | [003](adr/003-native-enum-state-machine.md) |
| An order freezes at submission, not at `payment_pending` | [004](adr/004-freezing-an-order-at-submission.md) |

## One real bug the tests caught

ADR 004 in full. In short: `isLocked()` originally left `submitted` and `quote_accepted`
open, so a per-yard price change in the admin panel silently repriced an order that had
already been submitted — ₦162,000 became ₦282,000 with nobody touching it. The rule was
wrong, not the test. `isLocked()` is now true for everything except `draft`.

## Not built yet

Phase 1 remainder, then Phase 2 as scheduled.

- **Paystack checkout.** The `payments` table, the `Payment` model with an idempotency key,
  and the `payment_pending → paid` transition are all in place. The gateway call, the webhook
  signature check and the amount verification are not.
- **Transactional email.** Notification classes and queued listeners.
- **Inspiration image pipeline.** Upload, storage and authorised retrieval work. The queued
  job that re-encodes to AVIF/WebP, strips EXIF and generates a blurhash does not exist yet;
  until it does, originals are stored on the private disk unchanged.
- **Phase 2 entirely:** Flutterwave fallback, deposit payments, WhatsApp Cloud API, CMS block
  editing in the admin, progress-photo upload, shipment tracking entry, SEO and sitemap.

## Before you sell anything

1. Replace the placeholder commercial figures — per-yard price per fabric, sewing cost per
   garment type, typical yardage, real shipping costs to your three main destinations.
2. Start the WhatsApp Business Cloud API application. Template approval takes days and it is
   the longest lead time in Phase 2.
3. Book the product photography. The fabric cards are the whole sale and they are currently
   flat colour swatches.

# DizzyMali — Bespoke Tailoring Web App
## Implementation Plan v1.0

**Prepared for:** Mohammed Ali, DizzyMali Fashion House
**Date:** 11 September 2026
**Stack:** Laravel 11 (API) · React 18 + TypeScript (Web) · MySQL 8 · Redis · S3-compatible object storage

---

## 1. Executive Summary

DizzyMali is a made-to-measure ordering platform for a Nigerian fashion house selling
Jalabiya, Kaftan, Agbada and Danshiki to customers in Nigeria and the diaspora.

The product problem is not "build a shop". It is: **a customer 4,000 km away must be able
to commission a garment with the same confidence as walking into the shop in person.**
Every design decision below serves that one goal — remove doubt about fit, remove doubt
about fabric, remove doubt about price, remove doubt about when it arrives.

### Decisions locked in this session

| Area | Decision |
|---|---|
| Payments | Paystack (primary) + Flutterwave (international fallback), multi-currency display |
| Currency | Prices stored in **NGN kobo** (integer). Display in NGN / USD / GBP / EUR via admin-set FX rate + margin |
| Measurements | Inches ↔ cm toggle, **saved reusable measurement profiles**, guided illustrations per field |
| Order tracking | 7-stage pipeline with customer-visible timeline |
| Notifications | Email + WhatsApp Cloud API + in-app |
| Shipping | Zone-based international rates, DHL/GIG/Aramex tracking numbers |
| Ready-to-wear | In scope, but **Phase 3** — bespoke flow must be flawless first |

> **Scope note:** you selected both "everything" and "keep v1 lean". I have resolved this by
> phasing rather than cutting: Phase 1 ships the lean bespoke core, Phase 2 adds shipping and
> notifications, Phase 3 adds ready-to-wear. Nothing is dropped; it is sequenced so you have a
> sellable product in ~6 weeks instead of ~16.

---

## 2. Product Architecture

```
┌──────────────────────────────────────────────────────────────────────┐
│                          CLIENTS                                      │
│  ┌────────────────────┐  ┌────────────────────┐  ┌────────────────┐  │
│  │  Storefront (SPA)  │  │  Admin Panel (SPA) │  │  Tailor PWA    │  │
│  │  React + TS + Vite │  │  React + TS + Vite │  │  (Phase 3)     │  │
│  └─────────┬──────────┘  └─────────┬──────────┘  └───────┬────────┘  │
└────────────┼───────────────────────┼─────────────────────┼───────────┘
             │  JSON:API over HTTPS  │  Sanctum SPA cookie auth
┌────────────▼───────────────────────▼─────────────────────▼───────────┐
│                      LARAVEL 11 APPLICATION                           │
│                                                                       │
│  HTTP Layer      Controllers · Form Requests · API Resources          │
│  ──────────────────────────────────────────────────────────────────   │
│  Domain Layer    Actions · Services · Policies · Events               │
│    • QuoteCalculator      • OrderStateMachine   • MeasurementValidator │
│    • FabricInventory      • CurrencyConverter   • ShippingRater        │
│  ──────────────────────────────────────────────────────────────────   │
│  Data Layer      Eloquent Models · Repositories · Migrations          │
│  ──────────────────────────────────────────────────────────────────   │
│  Infrastructure  Queues (Redis) · Scheduler · Media (S3) · Horizon    │
└──────┬─────────────────┬──────────────────┬───────────────┬──────────┘
       │                 │                  │               │
  ┌────▼────┐      ┌─────▼─────┐     ┌──────▼──────┐  ┌─────▼──────┐
  │ MySQL 8 │      │   Redis   │     │ S3 / Spaces │  │ 3rd party  │
  │         │      │ cache+queue│    │  media+CDN  │  │ PS/FW/WA/  │
  └─────────┘      └───────────┘     └─────────────┘  │ DHL/GIG    │
                                                       └────────────┘
```

### Why this shape

- **Headless Laravel + two React SPAs**, not Inertia monolith. The storefront needs to be
  fast and SEO-visible for diaspora discovery; the admin needs rich dashboard interactions.
  Separating them lets each evolve independently and lets a future mobile app reuse the API.
- **Actions/Services layer** rather than fat controllers. The pricing engine and order state
  machine are the two places where bugs cost real money — they must be unit-testable in
  isolation, with no HTTP involved.
- **Money as integers.** Every monetary value is stored as `BIGINT` kobo. No floats anywhere.
  This is non-negotiable; float arithmetic on N15,000/yard × 4.5 yards will eventually produce
  a customer-visible rounding error.

---

## 3. Technology & Conventions

### Backend
| Concern | Choice |
|---|---|
| Framework | Laravel 11, PHP 8.3 |
| Auth | Laravel Sanctum (SPA cookie) + optional Socialite (Google) |
| Roles | `spatie/laravel-permission` — customer, tailor, staff, admin, super-admin |
| Media | `spatie/laravel-medialibrary` + S3/DigitalOcean Spaces + Cloudflare CDN |
| Queues | Redis + Laravel Horizon |
| Search | MySQL full-text in v1; Meilisearch if catalogue exceeds ~2k SKUs |
| State machine | `spatie/laravel-model-states` for order status |
| Activity log | `spatie/laravel-activitylog` (audit trail — you will want this) |
| Testing | Pest v3, ≥80% coverage on domain layer |
| Static analysis | Larastan level 6, Laravel Pint |

### Frontend
| Concern | Choice |
|---|---|
| Build | Vite 5 + React 18 + TypeScript (strict) |
| Routing | React Router v6 |
| Server state | TanStack Query v5 |
| Client state | Zustand (order-wizard draft state only) |
| Forms | React Hook Form + Zod (schemas shared with backend contract) |
| Styling | Tailwind CSS 3 + CSS custom properties for design tokens |
| Components | shadcn/ui as the primitive base, restyled to DizzyMali tokens |
| Animation | Framer Motion (restrained — fashion, not fireworks) |
| Images | `<picture>` + AVIF/WebP, blurhash placeholders |
| Tests | Vitest + Testing Library; Playwright for the order-wizard E2E |

### Typography & colour (see §12 for the full token set)

- **Display:** *Bodoni Moda* — a high-contrast Didone. It is the letterform fashion houses
  have used for a century, it is a variable font (one file, the whole range), and it carries
  the editorial weight the garments deserve. Its italic doubles as the accent face for the
  single-word flourishes, which avoids adding a script font nobody can read at small sizes.
- **UI / labels:** *Jost* — a geometric sans in the Futura lineage. Set in uppercase with
  wide tracking for eyebrows, buttons and nav.
- **Body:** *DM Sans* — neutral, highly legible at 13–17px, pairs cleanly with the Didone.
- **Admin:** *IBM Plex Sans* throughout, with `font-variant-numeric: tabular-nums` on every
  money and measurement column.

All four are on Google Fonts, which matters: no licensing cost and no self-hosting step.

---

## 4. Data Model

### 4.1 Core entities

```
users ──┬── measurement_profiles ──── measurement_values
        ├── addresses
        ├── orders ──┬── order_items ──┬── order_item_measurements
        │            │                 ├── order_item_inspirations (media)
        │            │                 └── order_item_options
        │            ├── order_status_events
        │            ├── payments
        │            └── shipments
        └── carts (draft orders)

garment_types ──── garment_type_measurement_fields ──── measurement_fields
              └─── garment_type_pricing (sewing cost, default yardage)

fabrics ──┬── fabric_variants (colour / pattern, per-yard price, stock in yards)
          └── fabric_materials  (Cashmere, Linen, Cotton, Nylon)

settings ── currencies ── fx_rates ── shipping_zones ── shipping_rates
cms_blocks ── cms_media (heroes, sliders, carousels, videos, lookbook)
```

### 4.2 Key tables (abridged DDL intent)

**`garment_types`** — Jalabiya, Kaftan, Agbada, Danshiki
```
id, slug, name, description, hero_media_id,
base_sewing_cost_kobo, default_yardage (decimal 4,2),
requires_top_measurements (bool), requires_trouser_measurements (bool),
lead_time_days, is_active, sort_order
```
Agbada needs more yards than a Danshiki and more sewing labour — this is why yardage and
sewing cost live on the garment type, not as a global constant.

**`measurement_fields`** — the catalogue of every measurable dimension
```
id, group ('top'|'trouser'), key, label, help_text,
illustration_media_id, unit_type ('length'|'circumference'),
min_inches, max_inches, sort_order
```
Seeded with your exact list:
- **Top:** chest, shoulder, tommy (stomach), shirt_length, neck, sleeve, round_sleeve
- **Trouser:** waist, hip, thigh_lap, length, knee, foot

**`garment_type_measurement_fields`** — pivot; which fields apply to which garment, and
whether each is required. (Agbada has no trouser fields; a Kaftan two-piece has both.)

**`measurement_profiles`**
```
id, user_id, name ("My kaftan fit"), unit_preference ('in'|'cm'),
source ('manual'|'uploaded'|'tailor_assisted'),
uploaded_media_id (nullable — the photo/PDF of their tailor's sheet),
is_default, verified_at, notes
```

**`measurement_values`** — `profile_id, measurement_field_id, value_inches DECIMAL(5,2)`
Always stored in inches. The cm toggle is a presentation concern only. One canonical unit
in the database prevents the entire class of unit-mismatch bugs.

**`fabrics` / `fabric_variants`**
```
fabrics:          id, name, material_id, description, care_instructions,
                  origin, gsm, width_inches, is_active
fabric_variants:  id, fabric_id, colour_name, colour_hex, pattern,
                  swatch_media_id, price_per_yard_kobo,
                  stock_yards DECIMAL(8,2), min_order_yards, is_active
```
Price lives on the **variant**, not the fabric — the navy linen and the ivory linen from the
same bolt rarely cost the same.

**`orders`**
```
id, reference ('DZM-2609-A7K3'), user_id, status, currency_code,
fx_rate_used DECIMAL(14,6), subtotal_kobo, sewing_total_kobo,
fabric_total_kobo, shipping_kobo, discount_kobo, tax_kobo, total_kobo,
display_total_minor, shipping_address_id, notes, placed_at,
promised_at, completed_at
```
`fx_rate_used` is frozen at checkout. A customer in London who was quoted £180 pays £180
even if the naira moves before they complete payment.

**`order_items`**
```
id, order_id, garment_type_id, fabric_variant_id, quantity,
yards_used DECIMAL(5,2), measurement_profile_snapshot JSON,
sewing_cost_kobo, fabric_cost_kobo, line_total_kobo,
style_notes, tailor_id, status
```
`measurement_profile_snapshot` is a **frozen JSON copy**, not a foreign key. If the customer
edits their saved profile next year, the garment already cut must not change.

**`order_status_events`** — `order_id, from_status, to_status, actor_id, note, created_at`
Drives both the customer timeline and your internal audit trail.

### 4.3 Order state machine

```
 draft → submitted → quote_accepted → payment_pending → paid
       → fabric_sourced → cutting → sewing → quality_check
       → ready → shipped → delivered → closed
                       ↘ cancelled  ↘ refunded  ↘ on_hold
```
Customers see a simplified 5-stage view: **Received → Fabric & Cutting → Sewing → Quality
Check → On its way.** Internal granularity stays internal.

---

## 5. The Pricing Engine

This is the commercial heart of the app. It must be a pure, deterministic, unit-tested class.

### 5.1 Formula

```
For each line item:
  fabric_cost  = yards_required × variant.price_per_yard_kobo
  sewing_cost  = garment_type.base_sewing_cost_kobo
                 + Σ(selected_option.surcharge_kobo)        // embroidery, lining, etc.
                 + rush_surcharge (if express lead time)
  line_total   = (fabric_cost + sewing_cost) × quantity

Order:
  subtotal     = Σ line_total
  shipping     = ShippingRater(zone, total_weight_estimate, service_level)
  discount     = PromotionEngine(subtotal, coupon)
  tax          = 0 in v1 (revisit if VAT registration applies)
  total_kobo   = subtotal + shipping − discount + tax
  display_total = round_up_to_nearest(total_kobo × fx_rate × (1 + fx_margin), currency.rounding)
```

### 5.2 Yardage

`yards_required` defaults from `garment_type.default_yardage` but is **adjusted by size**:

```php
$yards = $garmentType->default_yardage
       + $this->sizeSurcharge($measurements, $garmentType)   // e.g. +0.5yd if chest > 46"
       + $customerOverride;                                   // customer may buy extra
```
An Agbada for a 52" chest genuinely needs more cloth than one for a 38" chest. Charging both
the same is either losing you money or overcharging small customers. Make the rule explicit
and admin-editable — a `yardage_rules` table of `(garment_type_id, field_key, threshold_inches,
additional_yards)`.

### 5.3 Everything is admin-configurable

Nothing above is hard-coded. The admin defines:
per-yard price (per fabric variant) · base sewing cost (per garment type) · option surcharges ·
yardage rules · express surcharge % · shipping zones and rates · FX rates and margin ·
minimum order value · deposit % (if you allow part-payment).

### 5.4 Quote transparency

The customer sees the breakdown before paying — fabric (4.5 yds × ₦15,000), sewing, delivery,
total. Opacity is what makes diaspora customers abandon. Showing the maths is a conversion
feature, not a disclosure obligation.

---

## 6. The Customer Order Flow

A 6-step wizard with a persistent live price panel. Draft auto-saves server-side after every
step, so a customer who closes the tab in Lagos resumes on their phone in Manchester.

**Step 1 — Garment.** Four large editorial cards: Jalabiya, Kaftan, Agbada, Danshiki. Each
shows lead time, "from ₦X", and a short line about the occasion it suits.

**Step 2 — Fabric.** Filter by material (Cashmere / Linen / Cotton / Nylon), colour, price
band. Large swatch photography — the whole sale depends on how good these images look. Each
variant card shows price per yard, stock status, and a "view on a finished garment" link to
the lookbook. Tapping a swatch opens a detail sheet with GSM, care, and drape notes.

**Step 3 — Measurements.** Three paths, presented as equal choices:
- **Use a saved profile** — one tap for repeat customers. This is the retention mechanic.
- **Enter measurements** — the top set (chest, shoulder, tommy, shirt length, neck, sleeve,
  round sleeve) and trouser set (waist, hip, thigh/lap, length, knee, foot), each with an
  illustration, a plain-language "how to measure this" line, and live sanity validation
  (a 12" chest is a typo; catch it before the fabric is cut).
- **Upload measurements** — photo or PDF of a tailor's sheet. Goes into a staff review queue;
  staff transcribe it into structured fields and it becomes a saved profile.
- Inches ⇄ cm toggle, remembered per user. Optional: "not sure? request a video fitting".

**Step 4 — Inspiration & style.** Upload up to 5 reference images (drag-drop, camera on
mobile, or paste a URL). Free-text style notes. Optional structured options — collar style,
embroidery level, pocket style, lining — each carrying a surcharge from the admin.

**Step 5 — Quantity, yards & delivery.** Yardage prefilled and explained ("An Agbada in your
size typically takes 5 yards"), adjustable. Delivery address, service level (standard /
express), and the promised date.

**Step 6 — Review & pay.** Full itemised quote in the customer's currency, terms, then
Paystack or Flutterwave checkout. Confirmation page with the order reference and the tracking
timeline.

**After the order:** order detail page with the stage timeline, photos your team uploads at
each stage (this is a *huge* trust builder for diaspora customers — a photo of their fabric
being cut), messaging thread with the workshop, and the shipment tracking number.

---

## 7. Admin Panel Modules

| Module | What it does |
|---|---|
| **Dashboard** | Revenue (today/week/month), orders by stage, fabric running low, orders past promised date, new customers |
| **Orders** | Kanban by stage + table view; open an order to see measurements, inspiration images, quote breakdown; advance stage; upload progress photos; internal notes; assign to a tailor; print a workshop job sheet |
| **Fabrics** | CRUD fabrics and variants, swatch upload, price per yard, stock in yards, low-stock threshold, bulk price update |
| **Garment types** | CRUD, sewing cost, default yardage, yardage rules, which measurement fields apply, lead time |
| **Options & surcharges** | Embroidery levels, collars, linings, express surcharge |
| **Measurements review** | Queue of uploaded measurement sheets awaiting transcription |
| **Customers** | Profiles, saved measurements, order history, lifetime value, notes |
| **Payments** | Transactions, gateway reconciliation, refunds, manual "mark as paid" for bank transfers |
| **Shipping** | Zones, rate tables, carriers, enter tracking numbers |
| **CMS / Media** | **Heroes, sliders, carousels, videos, lookbook galleries** — drag-to-reorder, schedule publish, alt text, responsive derivatives generated on upload |
| **Currencies** | Enabled currencies, FX rate (manual or auto-pulled daily), margin %, rounding rule |
| **Notifications** | Email/WhatsApp templates per stage, toggle per channel |
| **Staff & roles** | Users, roles, permissions, activity log |
| **Reports** | Sales by garment/fabric/country, fabric consumption, average lead time vs promised |

---

## 8. Media & Content Management

The storefront's job is to make the clothes look extraordinary. The CMS must therefore be
genuinely good, not an afterthought.

- **`cms_blocks`** — typed, positioned, schedulable content blocks: `hero`, `slider`,
  `carousel`, `video_feature`, `lookbook_grid`, `testimonial`, `promo_banner`.
  Each has `placement` (homepage, garment page, lookbook), `sort_order`, `starts_at`,
  `ends_at`, `is_active`, and a JSON `payload` validated per type.
- **Upload pipeline:** original → queued job → AVIF + WebP + JPEG at 5 widths → blurhash →
  CDN. Video: MP4/H.264 + poster frame; anything over 30s gets transcoded to HLS.
- **Hard rules:** alt text required on every image before publish; max upload 25 MB image /
  500 MB video; EXIF stripped (it contains GPS).

---

## 9. Payments, Currency & Trust

### Flow
1. Order submitted → server recalculates the quote (**never trust a client-sent price**)
2. Initialise transaction with Paystack; on card decline or non-NGN card, offer Flutterwave
3. Redirect/inline checkout → gateway webhook → verify signature → verify amount against the
   order → mark paid → dispatch notification jobs
4. Idempotency key on every payment attempt; webhook replays must be safe

### Multi-currency
- Base: NGN kobo. `currencies` table holds code, symbol, decimals, rounding rule, is_active.
- `fx_rates` updated daily by a scheduled job (exchangerate.host or your bank's rate), with an
  admin-set **margin %** on top to absorb volatility.
- Display currency chosen by the customer, defaulted from IP country.
- `fx_rate_used` frozen on the order at checkout.

### Deposit option
Worth building early: **60% deposit to start, 40% before shipping.** For a ₦400,000 Agbada
this materially raises conversion with diaspora customers who are buying from a brand they
have not yet touched.

---

## 10. Shipping & Logistics

- `shipping_zones` (Nigeria–Lagos, Nigeria–other, UK, EU, North America, Middle East, Rest of
  world) × `shipping_rates` (service level, weight band, price, transit days).
- Garment weight estimated from garment type + yards; admin can override per order.
- Carrier tracking numbers entered by admin (DHL, GIG, Aramex); customer sees a deep link.
- Customs: HS code and declared value on each shipment record — EU customers will be asked
  for duty, and saying so upfront prevents refused deliveries.

---

## 11. Notifications

| Trigger | Email | WhatsApp | In-app |
|---|---|---|---|
| Registration | ✓ | | |
| Order submitted / quote ready | ✓ | ✓ | ✓ |
| Payment received | ✓ | ✓ | ✓ |
| Stage change (cutting, sewing, QC) | | ✓ | ✓ |
| Progress photo uploaded | ✓ | ✓ | ✓ |
| Shipped + tracking | ✓ | ✓ | ✓ |
| Delivered / review request | ✓ | | ✓ |
| Abandoned draft (24h, 72h) | ✓ | | |

WhatsApp via the **Cloud API** with pre-approved message templates (template approval takes
days — start that application in week 1, not week 8). Email via Postmark or SES with a
verified sending domain, SPF/DKIM/DMARC configured.

---

## 12. Design System

### Colour tokens
```css
--dzm-cream:      #FAF6F0;   /* page ground */
--dzm-sand:       #EFE7DC;   /* card ground */
--dzm-ink:        #14110F;   /* primary text, buttons */
--dzm-ink-muted:  #6B625B;
--dzm-terracotta: #E2620E;   /* accent FILLS, rules, icons — never small text */
--dzm-accent-ink: #A8460A;   /* accent TEXT on cream/white — 5.0:1, passes AA */
--dzm-gold:       #C9A227;   /* premium markers, badges */
--dzm-success:    #1F8A5B;
--dzm-danger:     #C0392B;
--dzm-line:       #E2D8CB;
```
Cream + ink + terracotta reads as warm, editorial and unmistakably West African without
resorting to cliché. It also photographs well against the fabrics you actually sell.

### Type scale
Display Bodoni Moda 42/54/82 · Headings Bodoni Moda 21–46 · Body DM Sans 15/17 ·
UI and labels Jost 11–13 uppercase, 0.22em tracking · Admin IBM Plex Sans 11–24.

### Layout principles
- Generous whitespace; the product photography carries the page.
- 12-column grid, 1280px max content width, 24px gutters.
- Cards: 16px radius, 1px `--dzm-line` border, shadow only on hover.
- Buttons: ink fill for primary, terracotta fill for the single most important CTA per screen —
  with **ink text on the terracotta**, not white. White on #E2620E is 3.5:1 and fails AA at
  button sizes; ink on terracotta is 5.3:1 and reads more editorial anyway.
- Muted text is `--dzm-ink-muted` (#6B625B, 5.5:1). Never #A79D94 on a light ground — it is
  2.5:1. #A79D94 is for muted text on the ink background only.
- Minimum text size anywhere: 12px. Prices and measurements never go below that.
- Mobile-first. A large share of your Nigerian traffic is on a mid-range Android on 3G —
  target <200 KB initial JS and LCP under 2.5s on Moto G-class hardware.

---

## 13. Security, Privacy & Compliance

Given your IS-audit background, this section should be held to audit standard from day one.

- **NDPA 2023 / NDPR:** measurements and body photographs are personal data. Publish a privacy
  notice, capture explicit consent at registration, implement data-subject rights (export,
  rectify, erase) as actual admin functions, appoint a DPO contact, and keep a processing
  record. EU customers additionally invoke GDPR — same controls, higher penalty.
- **Uploads:** MIME sniffing not extension trust, size caps, EXIF strip, images re-encoded
  through the processing pipeline (kills polyglot payloads), stored on S3 **private** and
  served via signed URLs — never a public bucket.
- **AuthZ:** Laravel Policies on every model. A customer must never be able to read another
  customer's measurements by changing an ID. Add an automated test that proves this per model.
- **Payments:** webhook signature verification, amount verification against the order,
  idempotency keys, no card data ever touches your server (redirect/inline gateway only).
- **Transport & headers:** TLS 1.2+, HSTS, CSP, X-Content-Type-Options, Referrer-Policy.
- **Rate limiting:** login, registration, password reset, quote endpoint.
- **Audit:** activity log on orders, prices, fabrics, roles. Admin actions attributable.
- **Backups:** nightly encrypted MySQL dump off-site, 30-day retention, **restore tested
  monthly** — an untested backup is a rumour.
- **Secrets:** `.env` never committed; production secrets in the host's secret manager.

---

## 14. Testing Strategy

| Layer | Tool | Bar |
|---|---|---|
| Domain (pricing, state machine, yardage, FX) | Pest unit | 95% — this is where money lives |
| API | Pest feature tests | Every endpoint, incl. authz-denial cases |
| Frontend units | Vitest + RTL | Wizard steps, forms, price panel |
| E2E | Playwright | Register → order → pay (sandbox) → track |
| Visual | Playwright screenshots | Storefront key pages, both breakpoints |
| Load | k6 | 200 concurrent on catalogue + quote endpoint |
| Security | OWASP ASVS L2 checklist + `composer audit` / `npm audit` in CI |

**Golden test case:** Agbada, 4.5 yards of ₦15,000/yd linen, ₦45,000 sewing, ₦12,000 express
surcharge, ₦28,000 shipping to UK, 8% FX margin, GBP display. Write this as a fixture and
assert the exact expected total. Every pricing change must keep it green.

---

## 15. Environments & Deployment

| Env | Purpose | Host |
|---|---|---|
| local | Laravel Sail (Docker) | dev machine |
| staging | UAT, gateway sandbox keys | small VPS or Forge droplet |
| production | Live | Laravel Forge on DigitalOcean/Hetzner, or Ploi |

- Object storage: DigitalOcean Spaces or Cloudflare R2 (**R2 has no egress fee — meaningful
  when serving heavy fashion imagery to Europe**).
- CDN: Cloudflare in front of everything.
- CI/CD: GitHub Actions — Pint, Larastan, Pest, Vitest, build, deploy to staging on merge to
  `develop`, to production on tag.
- Monitoring: Sentry (both apps), Laravel Horizon dashboard, uptime check, daily queue-failure
  digest to your email.

---

## 16. Phased Roadmap

### Phase 0 — Foundations (Week 1)
Repo, Docker, CI, design tokens, component library skeleton, auth (register/login/verify/
reset), roles, S3 + media pipeline, admin shell.
**Milestone:** a user can register, log in, and an admin can log into an empty dashboard.

### Phase 1 — Bespoke core (Weeks 2–5) ← *the lean sellable product*
Garment types, fabrics + variants + swatches, measurement fields and profiles (in/cm,
validation, illustrations), inspiration uploads, the 6-step order wizard, the pricing engine,
admin CRUD for everything priced, order list + detail + stage advancement, Paystack checkout,
transactional email.
**Milestone:** a real customer can order a real Agbada and you can fulfil it.

### Phase 2 — Trust & reach (Weeks 6–8)
Multi-currency + FX job, Flutterwave fallback, deposit payments, shipping zones/rates/tracking,
customer order timeline + progress photos, WhatsApp notifications, measurement-sheet review
queue, CMS blocks (hero/slider/carousel/video/lookbook), SEO + OG images + sitemap.
**Milestone:** a customer in London orders in GBP and watches their garment being made.

### Phase 3 — Growth (Weeks 9–12)
Ready-to-wear catalogue with variants, sizes and cart; reviews and ratings; coupons and
referrals; wishlist; abandoned-draft recovery; reports; tailor PWA for the workshop; analytics.
**Milestone:** two revenue lines running on one platform.

### Phase 4 — Scale (ongoing)
Video fitting bookings, AI fabric recommendation from an inspiration image, loyalty tier,
multi-tailor marketplace, mobile app on the same API.

---

## 17. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Wrong measurements → unwearable garment → refund + reputation | High | Range validation, illustrations, staff review of uploads, "confirm measurements" email before cutting, video-fitting option |
| Naira volatility erodes margin on foreign orders | High | FX margin %, daily rate refresh, rate frozen at checkout only |
| Poor product photography kills conversion | High | Budget a proper shoot before launch; the CMS is only as good as what you put in it |
| WhatsApp template approval delay | Medium | Apply in week 1; email is the fallback channel |
| Fabric sold out after order placed | Medium | Decrement reserved yards at checkout; low-stock alerts; suggest alternatives |
| Customs duty surprise in EU | Medium | State duty responsibility at checkout; correct HS codes |
| Scope creep from Phase 3 into Phase 1 | Medium | Phase gates; ready-to-wear does not start before bespoke is live |

---

## 18. Immediate Next Actions

1. Register the domain and set up email sending (SPF/DKIM/DMARC) — DNS propagation is slow.
2. Apply for Paystack and Flutterwave business accounts (KYC takes days).
3. Start the WhatsApp Business Cloud API application.
4. Book the product photography shoot for the four garment types and the fabric swatches.
5. Give me your actual numbers: per-yard price per fabric, sewing cost per garment type,
   typical yardage per garment, shipping costs to your three main destinations. The pricing
   engine is built against real figures, not placeholders.
6. Run Phase 0 with the agent team in `/.claude/agents` (see `AGENT_TEAM.md`).

---

*Plan prepared with Claude. Figures marked as examples are placeholders pending your real
cost inputs.*

# DizzyMali — Project Instructions

Bespoke tailoring platform for DizzyMali Fashion House (Nigeria). Customers commission
Jalabiya, Kaftan, Agbada and Danshiki online. Audience: Nigeria (incl. upcountry), the
diaspora and Europe.

**Stack:** Laravel 11 / PHP 8.3 API · React 18 + TypeScript SPAs (storefront + admin) ·
MySQL 8 · Redis · S3-compatible storage · Sanctum SPA auth.

**Read `docs/IMPLEMENTATION_PLAN.md` before designing or building anything.**
Build API work against `docs/api/openapi.yaml`.

## Non-negotiable rules

1. **Money is integer kobo.** Columns are `BIGINT` named `*_kobo`. No float arithmetic on
   money, ever. No `round()` on a float total.
2. **Measurements are stored in inches** as `DECIMAL(5,2)`. The inches/cm toggle is a
   presentation concern handled in the frontend.
3. **The server recalculates every quote.** A price arriving from the client is input to be
   validated against, never a value to be trusted.
4. **Every Eloquent model has a Policy**, and every Policy has a test proving user A cannot
   access user B's record. Measurements and customer photographs are personal data under the
   NDPA 2023 — treat an IDOR here as a reportable breach.
5. **Historic orders are immutable.** Measurements, item prices and the FX rate are snapshotted
   onto the order at submission. Editing a saved profile must never alter a garment already cut.
6. **Side effects are queued.** Email, WhatsApp, image processing and stock updates go through
   queued listeners, never inline in the request cycle.
7. **Controllers are thin.** Validate in a Form Request, delegate to an Action in
   `app/Actions/`, return an API Resource.
8. **No secrets in the repo.** Ever.

## Design tokens

```
cream #FAF6F0 · sand #EFE7DC · ink #14110F · ink-muted #6B625B
terracotta #E2620E (fills/icons) · accent-ink #A8460A (accent TEXT) · gold #C9A227
success #166B47 · danger #C0392B · line #E2D8CB
Display + accent italic: Bodoni Moda · UI/labels: Jost · Body: DM Sans
Admin: IBM Plex Sans with tabular numerals on numeric columns
(all four on Google Fonts)
Radius 16 (cards) / 10 (inputs) / 999 (pills) · Grid 12-col, 1280 max, 24px gutters
Spacing 4 8 12 16 24 32 48 64 96
```

Never hard-code a hex. Use the token.

Contrast rules that are easy to get wrong: terracotta text on cream is 3.3:1 — use
`accent-ink` for any accent TEXT and keep `terracotta` for fills, rules and icons. Put **ink**
text on a terracotta fill, never white. `#A79D94` is a muted colour for the ink background
only; on cream or white use `ink-muted`. Nothing is set below 12px.

## Quality gates

Before anything is "done": Pint clean · Larastan level 6 clean · Pest passing · Vitest passing ·
no `any` in TypeScript · responsive at 375/768/1280 · loading, empty and error states present.

## Performance budget

Storefront initial JS under 200 KB gzipped, LCP under 2.5s on a mid-range Android over 3G.
A large share of Nigerian traffic is exactly that device on exactly that network.

# DizzyMali — Agent Team

Fourteen specialist agents live in `.claude/agents/`. Drop that folder at the root of the
DizzyMali repository and Claude Code picks them up automatically. Invoke one by name
("use the pricing-engine agent to…") or let Claude delegate based on each agent's description.

## The team

| Agent | Owns | Model |
|---|---|---|
| `product-architect` | Data model, module boundaries, API contract, ADRs | opus |
| `ui-designer` | Claude Design canvases, design system, component specs | opus |
| `laravel-api` | Laravel models, migrations, controllers, policies, jobs | sonnet |
| `pricing-engine` | Quote calculation, yardage rules, FX, rounding | opus |
| `payments-integration` | Paystack, Flutterwave, multi-currency, webhooks, refunds | opus |
| `react-storefront` | Customer SPA — homepage, catalogue, order wizard, tracking | sonnet |
| `admin-panel` | Admin SPA — dashboard, orders, fabrics, CMS, reports | sonnet |
| `media-cms` | Upload pipeline, derivatives, CDN, hero/slider/carousel blocks | sonnet |
| `notifications` | Email, WhatsApp Cloud API, in-app, templates | sonnet |
| `data-seeder` | Migrations, factories, reference data, demo dataset | sonnet |
| `qa-tester` | Pest, Vitest, Playwright, load tests, defect reports | sonnet |
| `security-auditor` | OWASP ASVS, NDPA/NDPR/GDPR, IDOR, uploads, secrets | opus |
| `devops-deploy` | Docker, CI/CD, Forge, Horizon, monitoring, backups | sonnet |
| `docs-writer` | API reference, admin guides, runbooks, privacy docs | sonnet |

## Why these boundaries

The split follows **where bugs are expensive**, not where code files sit.

- Pricing and payments are separated from general backend work and given `opus`, because an
  error in either costs money directly and is often invisible until a customer complains.
- Security is a standing reviewer rather than a checklist item, because measurements and
  customer photographs are personal data under the NDPA — and because an IDOR on
  `/api/measurement-profiles/{id}` is the single most likely real breach in a system like this.
- Design comes before frontend, not after. `ui-designer` publishes a canvas; the two frontend
  agents implement against it. This stops each screen inventing its own spacing scale.
- `product-architect` publishes the OpenAPI contract before either side codes, so backend and
  frontend can proceed in parallel without blocking on each other.

## How to run a phase

```
1. product-architect   → schema + OpenAPI contract + ADRs for the phase
2. ui-designer         → Claude Design canvas for the phase's screens
3. data-seeder         → migrations, factories, reference data
4. laravel-api         ─┐
   pricing-engine       ├─ run in parallel, all against the published contract
   react-storefront     │
   admin-panel         ─┘
5. qa-tester           → tests; nothing is "done" while one is red
6. security-auditor    → findings register; Critical and High close on evidence, not promises
7. docs-writer         → admin guide + runbook for what shipped
8. devops-deploy       → ship to staging, then production
```

Steps 1–3 are sequential because everything downstream depends on their output. Step 4 is where
the parallelism pays off. Steps 5–6 are gates, not formalities.

## Ground rules every agent inherits

Put these in the repo's root `CLAUDE.md` so they apply to every session:

1. **Money is integer kobo.** No floats in monetary arithmetic, anywhere.
2. **Measurements are stored in inches.** The cm toggle is presentation only.
3. **The server recalculates every price.** A client-supplied amount is never trusted.
4. **Every model has a Policy and every Policy has a denial test.**
5. **Historic orders are immutable.** Measurements, prices and FX rates are snapshotted.
6. **Side effects go on the queue.** Never in the request cycle.
7. **Read `docs/IMPLEMENTATION_PLAN.md` before designing anything new.**
8. **Build to `docs/api/openapi.yaml`.** If the contract is wrong, fix the contract first.

## Adding to the team later

Phase 3 and 4 will likely want:
- `tailor-pwa` — the workshop-floor app for tailors updating stages from a phone
- `seo-content` — programmatic pages for "bespoke agbada London", diaspora search terms
- `analytics` — GA4/Plausible events, funnel instrumentation on the wizard
- `i18n` — French and Arabic, if you push into francophone West Africa or the Gulf
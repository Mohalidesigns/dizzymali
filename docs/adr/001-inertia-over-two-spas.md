# ADR 001 — Laravel + Inertia + React, not a headless API with two SPAs

**Status:** Accepted · 12 September 2026
**Supersedes:** §2 of `IMPLEMENTATION_PLAN.md` ("Headless Laravel + two React SPAs")

## Context

The plan specifies a headless Laravel JSON API with two independent React SPAs — a
storefront and an admin — authenticating over Sanctum SPA cookies. The reasoning given was
SEO for diaspora discovery, rich admin interactions, and reuse of the API by a future
mobile app.

Against that, Phase 1 has a six-week target and one developer. Two SPAs plus a contract
means: two Vite builds, a CORS and Sanctum stateful-domain configuration that is a
well-known source of lost afternoons, an OpenAPI document that must be kept honest by
hand, duplicated auth plumbing, duplicated design-token wiring, and every endpoint written
twice — once as a controller and once as a typed client.

## Options considered

1. **Headless API + two SPAs**, as planned. Truest to the document. Highest ceremony.
2. **Laravel + Inertia + React.** One application, React pages, no client-side API layer,
   no CORS, no token handling. The domain layer — pricing engine, state machine, actions,
   policies — is byte-for-byte identical either way.
3. **Blade + Livewire.** Least JavaScript, but the order wizard's live price panel and the
   admin's dense tables are exactly the interactions React is better at.

## Decision

Option 2. Laravel 12 + Inertia 2 + React 18 + TypeScript, one application, two layouts.

This is not a retreat from the API. Everything that matters lives in `app/Domain` and
`app/Actions`, entirely free of HTTP. Adding `routes/api.php` for the Phase 4 mobile app
means writing controllers that call the same Actions — a day's work, not a rewrite. What we
avoid is paying the two-SPA tax for three phases before the thing it buys us exists.

SEO is unaffected: Inertia supports server-side rendering (`resources/js/ssr.tsx` is in
place), which gives the storefront better crawlability than a client-rendered SPA would.

## Consequences

- No CORS, no Sanctum SPA configuration, no token refresh logic.
- Route names are shared between server and client through Ziggy.
- Forms post to Laravel and validation errors come back through Inertia, so the 422-handling
  layer every SPA has to write does not exist here.
- `docs/api/openapi.yaml` is deferred until the API surface actually has an external
  consumer. Writing it now would document endpoints nobody calls.
- If a second frontend is ever needed, the domain layer is ready for it. The cost is
  deferred, not accrued.

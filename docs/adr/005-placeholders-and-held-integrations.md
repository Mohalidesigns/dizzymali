# ADR 005 — Unconfigured integrations are a first-class state, not a stub

**Status:** Accepted · 12 September 2026

## Context

Four things Phase 2 depends on do not exist yet, and all four are waiting on
someone other than the developer:

| Waiting on | Blocked by |
|---|---|
| Flutterwave credentials | Business account approval |
| WhatsApp Cloud API | Meta template approval, which takes days |
| SMTP credentials | Sending domain and DNS |
| Product photography | A shoot that has not been booked |

The obvious move is to stub each one and come back later. The obvious move is wrong, for a
reason that only shows up at the worst moment: a stub is untested code that has never run
against the real path, and the day the credentials arrive is the day you discover what it
does not do. Worse, a stub that returns success is how an order gets marked paid without
anybody being paid.

## Decision

Build each integration completely, and make "no credentials yet" an explicit, tested,
visible state.

**Payments.** `PaymentGateway` has `isConfigured()`. `GatewayManager` skips gateways that
return false when choosing one, and `forCurrency()` returns `null` rather than a broken
gateway — so the checkout page says "we cannot take cards in this currency yet" instead of
failing at the card form. Calling an unconfigured gateway directly throws
`GatewayNotConfigured`; it never quietly succeeds. The admin payments screen lists every
gateway with its status, because "awaiting credentials" is something you want to see, not
something to discover.

**WhatsApp.** `WhatsAppDriver` has two implementations. `CloudApiWhatsAppDriver` is the real
Meta integration, complete. `LogWhatsAppDriver` is the default, and writes the exact message
that would have gone out to the log. The full path — event, queued listener, channel, opt-in
check, phone normalisation, template parameters — runs today and is tested today. Switching
on is three lines of `.env`. Replacing Meta with another provider is one interface.

Template names live in config rather than code, because Meta decides what they are called
and that decision has not been made. The application only ever refers to a key.

**Email.** `MAIL_MAILER=log` writes every message to `storage/logs`, so the templates can be
read and reviewed before a single credential exists.

**Photography.** Every catalogue image falls back to `Placeholder`, which generates an SVG
from the record's own slug and the DizzyMali palette: deterministic, so the same fabric is
the same swatch on every load; inline, so it costs no request; and labelled, so staff can
see what is still missing. Uploading a real image replaces it with no other change.

## Consequences

- Everything in Phase 2 can be demonstrated end to end today, with nothing mocked out of the
  path that will run in production.
- Switching each one on is configuration. There is no "integration week" waiting at the end.
- The tests cover the unconfigured state as deliberately as the configured one, including
  that an unconfigured gateway is skipped rather than used, and that a customer who never
  opted in to WhatsApp is never messaged.
- The cost is a little more code than a stub. It is a lot less code than the rewrite a stub
  turns into.

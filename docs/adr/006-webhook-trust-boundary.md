# ADR 006 — A webhook proves origin; only verification proves outcome

**Status:** Accepted · 12 September 2026

## Context

A payment webhook is an unauthenticated HTTP request from the open internet, arriving at a
route that has no session and is necessarily exempt from CSRF. It is the single most
attractive endpoint in the application: a request that persuades it succeeds in marking an
order paid.

## Decision

Three independent checks, each of which must pass.

**1. Signature, in constant time.** Paystack signs the body with HMAC-SHA512 over the secret
key; Flutterwave sends a shared secret as a header. Both are compared with `hash_equals`,
never `===`. A byte-by-byte comparison that returns early leaks the correct value one
character at a time, and that is a demonstrated attack on HMAC webhooks, not a theoretical
one. A failed signature returns `401` with an empty body — telling a forged request why it
was refused is free reconnaissance.

**2. Re-verification against the gateway's API.** Even a correctly signed webhook is only
used to learn *which* transaction to look at. The outcome comes from calling the gateway's
own verify endpoint. The signature proves the message came from the gateway; only
verification proves what the gateway actually did.

**3. Amount match, exactly.** `SettlePayment` compares the verified amount and currency
against the payment row we created from the server-calculated order total. Not "at least",
not "close enough" — exactly. A gateway reporting more than we asked for is as wrong as one
reporting less, and both mean something needs a human. A mismatch marks the payment failed,
logs the expected and reported figures, and leaves the order unpaid.

Settlement is idempotent throughout: webhooks retry, and the customer frequently lands on
the return URL at the same moment the webhook arrives. Settling an already-settled payment
returns early rather than doubling `amount_paid_kobo`.

## Consequences

- A forged webhook cannot mark an order paid, and a tampered amount cannot either.
- Both are proved by tests: a signature computed over a different body is rejected, and a
  verification claiming ₦1 against a ₦151,750 payment marks the payment failed and leaves
  the order alone.
- The return URL is treated as a hint, not a result — anyone can visit it, so it triggers the
  same verification rather than trusting its query string.
- Cost: one extra HTTP call per settlement. Against the alternative, this is not a
  trade-off worth discussing.

# ADR 003 — The order state machine is a native enum, not spatie/laravel-model-states

**Status:** Accepted · 12 September 2026
**Diverges from:** `IMPLEMENTATION_PLAN.md` §3, which lists `spatie/laravel-model-states`

## Context

The order pipeline has sixteen states — thirteen on the happy path plus on-hold, cancelled
and refunded — and the customer sees five. Transitions must be validated, recorded, and
attributable.

`spatie/laravel-model-states` models each state as a class, with transition classes between
them. For a workflow with genuinely different *behaviour* per state — different validation,
different available actions, polymorphic dispatch — that structure earns its keep.

Ours does not work that way. Every state behaves identically; what differs is only which
state may follow which, and what side effect fires on arrival. Sixteen state classes plus
transition classes would be roughly forty files expressing a table that fits on one screen.

## Decision

`App\Enums\OrderStatus`, a native backed enum, owns:

- `allowedTransitions(): list<self>` — the transition table, in one `match`.
- `canTransitionTo(self): bool`
- `customerStage(): CustomerStage` — the thirteen-to-five collapse.
- `isLocked()`, `isTerminal()`, `isPaid()` — the predicates the rest of the app asks about.

`App\Domain\Orders\OrderStateMachine` is the only class in the application permitted to write
`orders.status`. It validates against the enum, writes the row and the `order_status_events`
record in one transaction, and throws `IllegalOrderTransition` otherwise. Side effects that
belong to a stage — releasing reserved fabric on cancellation, consuming it when the bolt is
cut — live in `AdvanceOrderStage`, not in the machine.

`spatie/laravel-model-states` is consequently **not** installed.

## Consequences

- The entire transition table is readable in one file, which matters when the question is
  "can a customer still cancel this?" and the answer must be certain.
- It is directly unit-testable with no database: `OrderStatusTest` walks the whole happy
  path, asserts every illegal jump, and asserts that nothing already being cut can be
  cancelled.
- Casting `status` to the enum on the model gives type safety at every call site.
- If per-state behaviour ever genuinely diverges, moving to state classes is mechanical —
  the transition table is already isolated.

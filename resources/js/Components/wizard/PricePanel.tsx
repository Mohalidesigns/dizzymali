import { Button } from '../Ui'
import type { Order } from '../../types'

/**
 * The live price panel, pinned beside the wizard.
 *
 * Showing the maths is a conversion feature, not a disclosure obligation —
 * opacity is what makes a customer 4,000 km away abandon the basket.
 */
export default function PricePanel({
  order,
  busy,
  canSubmit,
  onSubmit,
}: {
  order: Order
  busy: boolean
  canSubmit: boolean
  onSubmit: () => void
}) {
  const item = order.items[0] ?? null

  return (
    <aside
      aria-label="Your quote"
      className="sticky top-24 rounded-[--radius-card] border border-line bg-white p-6"
    >
      <p className="eyebrow text-ink">Your quote</p>
      <p className="mt-1 font-ui text-[12px] uppercase tracking-[0.14em] text-ink-faint">
        {order.reference}
      </p>

      <dl className="tabular mt-5 space-y-2 text-[14px]">
        <Line label="Fabric" value={order.totals.fabric.formatted} />
        <Line label="Sewing" value={order.totals.sewing.formatted} />
        {order.totals.options.minor > 0 ? (
          <Line label="Options" value={order.totals.options.formatted} />
        ) : null}
        <Line label="Delivery" value={order.totals.shipping.formatted} />
      </dl>

      <div className="mt-4 flex items-baseline justify-between border-t border-line pt-4">
        <span className="font-ui text-[12px] uppercase tracking-[0.14em]">Total</span>
        <span className="tabular font-display text-2xl">{order.totals.total.formatted}</span>
      </div>

      {order.currency_code !== 'NGN' && order.totals.display_total ? (
        <p className="tabular mt-1 text-right text-[13px] text-ink-muted">
          ≈ {order.totals.display_total.formatted}
        </p>
      ) : null}

      {item && item.yards.total > 0 ? (
        <p className="mt-4 text-[13px] text-ink-muted">
          {item.yards.total} yd of cloth
          {item.yards.size_adjustment > 0
            ? `, including ${item.yards.size_adjustment} yd extra for your measurements`
            : ''}
          .
        </p>
      ) : null}

      <Button
        variant="accent"
        className="mt-6 w-full"
        disabled={!canSubmit || busy}
        onClick={onSubmit}
      >
        {busy ? 'Working…' : 'Place this order'}
      </Button>

      <p className="mt-3 text-center text-[12px] text-ink-muted">
        Your draft saves automatically. Come back any time.
      </p>
    </aside>
  )
}

function Line({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-baseline justify-between gap-4">
      <dt className="text-ink-muted">{label}</dt>
      <dd>{value}</dd>
    </div>
  )
}

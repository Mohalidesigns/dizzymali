import { Card, ErrorNote, Eyebrow } from '../Ui'
import { shortDate } from '../../lib/format'
import type { Order } from '../../types'

export default function StepReview({ order, errors }: { order: Order; errors?: string[] }) {
  const item = order.items[0] ?? null

  return (
    <div>
      <Eyebrow>Step 6 of 6</Eyebrow>
      <h2 className="mt-3 font-display text-4xl">Check it over</h2>
      <p className="mt-2 max-w-xl text-ink-muted">
        Every figure below is calculated on our side from the catalogue price of the cloth and the
        yardage your measurements call for. Nothing is rounded in our favour.
      </p>

      {errors && errors.length > 0 ? (
        <div className="mt-6">
          <ErrorNote>
            <p className="font-medium">This order is not quite ready:</p>
            <ul className="mt-2 list-inside list-disc space-y-1">
              {errors.map((e) => (
                <li key={e}>{e}</li>
              ))}
            </ul>
          </ErrorNote>
        </div>
      ) : null}

      {item ? (
        <Card className="mt-8">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h3 className="font-display text-3xl">{item.garment_type?.name}</h3>
              <p className="mt-1 text-[15px] text-ink-muted">
                {item.fabric_variant?.name ?? 'No fabric chosen'} · {item.quantity} ×
              </p>
            </div>
            {item.fabric_variant?.colour_hex ? (
              <span
                className="h-12 w-12 rounded-full border border-line"
                style={{ backgroundColor: item.fabric_variant.colour_hex }}
                aria-hidden="true"
              />
            ) : null}
          </div>

          <dl className="tabular mt-6 space-y-2 text-[15px]">
            <Row
              label={`Fabric — ${item.yards.total} yd`}
              sub={
                item.yards.size_adjustment > 0
                  ? `${item.yards.base} base + ${item.yards.size_adjustment} for your size${item.yards.customer_extra ? ` + ${item.yards.customer_extra} extra` : ''}`
                  : undefined
              }
              value={item.costs.fabric.formatted}
            />
            <Row label="Sewing" value={item.costs.sewing.formatted} />
            {item.options.map((o) => (
              <Row key={`${o.group}-${o.name}`} label={`${o.group}: ${o.name}`} value={o.surcharge.formatted} />
            ))}
          </dl>

          {item.measurements.length > 0 ? (
            <details className="mt-6 border-t border-line pt-4">
              <summary className="cursor-pointer font-ui text-[12px] uppercase tracking-[0.14em] text-accent-ink">
                Measurements we will cut to
              </summary>
              <dl className="tabular mt-4 grid grid-cols-2 gap-x-8 gap-y-1 text-[14px] sm:grid-cols-3">
                {item.measurements.map((m) => (
                  <div key={m.key} className="flex justify-between">
                    <dt className="text-ink-muted">{m.label}</dt>
                    <dd>{m.value_inches}&quot;</dd>
                  </div>
                ))}
              </dl>
            </details>
          ) : null}

          {item.style_notes ? (
            <div className="mt-6 border-t border-line pt-4">
              <h4 className="eyebrow text-ink">Your notes</h4>
              <p className="mt-2 text-[15px] text-ink-muted">{item.style_notes}</p>
            </div>
          ) : null}
        </Card>
      ) : null}

      <Card className="mt-6">
        <h3 className="eyebrow text-ink">Total</h3>
        <dl className="tabular mt-4 space-y-2 text-[15px]">
          <Row label="Subtotal" value={order.totals.subtotal.formatted} />
          <Row label="Delivery" value={order.totals.shipping.formatted} />
          {order.totals.discount.minor > 0 ? (
            <Row label="Discount" value={`− ${order.totals.discount.formatted}`} />
          ) : null}
          <div className="flex items-baseline justify-between border-t border-line pt-3 text-[20px] font-medium">
            <dt>Total</dt>
            <dd>{order.totals.total.formatted}</dd>
          </div>
          {order.currency_code !== 'NGN' && order.totals.display_total ? (
            <p className="pt-1 text-[13px] text-ink-muted">
              Charged as {order.totals.display_total.formatted}. This rate is fixed the moment you pay —
              it will not change if the naira moves.
            </p>
          ) : null}
        </dl>

        {order.shipping_address ? (
          <div className="mt-6 border-t border-line pt-4">
            <h4 className="eyebrow text-ink">Shipping to</h4>
            <p className="mt-2 text-[15px] text-ink-muted">
              {Object.values(order.shipping_address).filter(Boolean).join(', ')}
            </p>
          </div>
        ) : null}

        {order.promised_at ? (
          <p className="mt-4 text-[14px] text-ink-muted">
            Expected to be finished by <strong className="text-ink">{shortDate(order.promised_at)}</strong>.
          </p>
        ) : null}
      </Card>
    </div>
  )
}

function Row({ label, value, sub }: { label: string; value: string; sub?: string }) {
  return (
    <div className="flex items-baseline justify-between gap-6 border-b border-line pb-2">
      <dt>
        {label}
        {sub ? <span className="block text-[13px] text-ink-muted">{sub}</span> : null}
      </dt>
      <dd>{value}</dd>
    </div>
  )
}

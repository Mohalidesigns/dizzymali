import { Head } from '@inertiajs/react'
import { Badge, Card, Eyebrow } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import { cx, shortDate } from '../../lib/format'
import type { Order } from '../../types'

export default function OrderDetail({ order: raw }: { order: { data: Order } | Order }) {
  const order = 'data' in raw ? raw.data : raw

  return (
    <StorefrontLayout>
      <Head title={`Order ${order.reference}`} />

      <header className="mb-10 flex flex-wrap items-end justify-between gap-6">
        <div>
          <Eyebrow>{order.reference}</Eyebrow>
          <h1 className="mt-2 font-display text-[clamp(2rem,5vw,3.2rem)]">
            {order.items[0]?.garment_type?.name ?? 'Your order'}
          </h1>
          <p className="mt-2 text-ink-muted">{order.timeline.current_description}</p>
        </div>
        <div className="text-right">
          <p className="tabular font-display text-3xl">{order.totals.total.formatted}</p>
          {order.promised_at ? (
            <p className="mt-1 text-[13px] text-ink-muted">Promised {shortDate(order.promised_at)}</p>
          ) : null}
        </div>
      </header>

      <Card>
        <h2 className="eyebrow text-ink">Where it is</h2>
        <ol className="mt-6 grid gap-4 sm:grid-cols-5">
          {order.timeline.stages.map((stage, index) => (
            <li key={stage.key} className="relative">
              <div
                className={cx(
                  'h-1 rounded-full',
                  stage.reached ? 'bg-terracotta' : 'bg-line',
                )}
              />
              <p
                className={cx(
                  'mt-3 font-ui text-[12px] uppercase tracking-[0.14em]',
                  stage.reached ? 'text-ink' : 'text-ink-faint',
                )}
              >
                {index + 1}. {stage.label}
              </p>
              <p className="mt-1 text-[13px] text-ink-muted">{stage.description}</p>
            </li>
          ))}
        </ol>
      </Card>

      <div className="mt-8 grid gap-8 lg:grid-cols-[1fr_340px]">
        <div className="space-y-6">
          {order.items.map((item) => (
            <Card key={item.id}>
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h3 className="font-display text-2xl">{item.garment_type?.name}</h3>
                  <p className="mt-1 text-[14px] text-ink-muted">{item.fabric_variant?.name}</p>
                </div>
                {item.fabric_variant?.colour_hex ? (
                  <span
                    className="h-10 w-10 rounded-full border border-line"
                    style={{ backgroundColor: item.fabric_variant.colour_hex }}
                    aria-hidden="true"
                  />
                ) : null}
              </div>

              <dl className="tabular mt-5 space-y-1 text-[14px]">
                <div className="flex justify-between border-b border-line pb-1">
                  <dt className="text-ink-muted">Fabric ({item.yards.total} yd)</dt>
                  <dd>{item.costs.fabric.formatted}</dd>
                </div>
                <div className="flex justify-between border-b border-line pb-1">
                  <dt className="text-ink-muted">Sewing</dt>
                  <dd>{item.costs.sewing.formatted}</dd>
                </div>
                {item.options.map((o) => (
                  <div key={`${o.group}-${o.name}`} className="flex justify-between border-b border-line pb-1">
                    <dt className="text-ink-muted">{o.group}: {o.name}</dt>
                    <dd>{o.surcharge.formatted}</dd>
                  </div>
                ))}
              </dl>

              {item.measurements.length > 0 ? (
                <details className="mt-5">
                  <summary className="cursor-pointer font-ui text-[12px] uppercase tracking-[0.14em] text-accent-ink">
                    Measurements used
                  </summary>
                  <dl className="tabular mt-3 grid grid-cols-2 gap-x-8 gap-y-1 text-[14px] sm:grid-cols-3">
                    {item.measurements.map((m) => (
                      <div key={m.key} className="flex justify-between">
                        <dt className="text-ink-muted">{m.label}</dt>
                        <dd>{m.value_inches}&quot;</dd>
                      </div>
                    ))}
                  </dl>
                  <p className="mt-3 text-[12px] text-ink-faint">
                    Frozen when you placed this order. Editing your saved profile will not change it.
                  </p>
                </details>
              ) : null}
            </Card>
          ))}

          {order.progress_photos && order.progress_photos.length > 0 ? (
            <Card>
              <h3 className="eyebrow text-ink">From the workshop</h3>
              <ul className="mt-4 space-y-3">
                {order.progress_photos.map((p) => (
                  <li key={p.id} className="flex items-baseline justify-between gap-4 border-b border-line pb-2 text-[14px]">
                    <span>{p.caption ?? p.alt_text}</span>
                    <span className="text-ink-muted">{shortDate(p.at)}</span>
                  </li>
                ))}
              </ul>
            </Card>
          ) : null}
        </div>

        <aside className="space-y-6">
          <Card>
            <h3 className="eyebrow text-ink">Payment</h3>
            <dl className="tabular mt-4 space-y-2 text-[14px]">
              <div className="flex justify-between">
                <dt className="text-ink-muted">Total</dt>
                <dd>{order.totals.total.formatted}</dd>
              </div>
              <div className="flex justify-between">
                <dt className="text-ink-muted">Paid</dt>
                <dd>{order.totals.paid.formatted}</dd>
              </div>
              <div className="flex justify-between border-t border-line pt-2 font-medium">
                <dt>Balance</dt>
                <dd>{order.totals.balance_due.formatted}</dd>
              </div>
            </dl>
            <div className="mt-4">
              <Badge tone={order.totals.balance_due.minor === 0 ? 'success' : 'warn'}>
                {order.totals.balance_due.minor === 0 ? 'Settled' : 'Balance outstanding'}
              </Badge>
            </div>
          </Card>

          {order.events && order.events.length > 0 ? (
            <Card>
              <h3 className="eyebrow text-ink">History</h3>
              <ol className="mt-4 space-y-3">
                {order.events.map((e) => (
                  <li key={e.id} className="border-l-2 border-line pl-3 text-[14px]">
                    <p className="font-medium">{e.label}</p>
                    <p className="text-[13px] text-ink-muted">{shortDate(e.at)}</p>
                    {e.note ? <p className="mt-1 text-[13px] text-ink-muted">{e.note}</p> : null}
                  </li>
                ))}
              </ol>
            </Card>
          ) : null}
        </aside>
      </div>
    </StorefrontLayout>
  )
}

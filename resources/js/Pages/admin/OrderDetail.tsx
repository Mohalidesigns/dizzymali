import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import { shortDate } from '../../lib/format'
import type { Order } from '../../types'

export default function OrderDetail({
  order: raw,
  allowedTransitions,
  tailors,
}: {
  order: { data: Order } | Order
  allowedTransitions: Array<{ value: string; label: string }>
  tailors: Array<{ id: number; name: string }>
}) {
  const order = 'data' in raw ? raw.data : raw
  const [target, setTarget] = useState(allowedTransitions[0]?.value ?? '')
  const [note, setNote] = useState('')

  return (
    <AdminLayout title={`Order ${order.reference}`}>
      <Head title={order.reference} />

      <div className="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div className="space-y-6">
          {order.items.map((item) => (
            <section key={item.id} className="rounded-xl border border-line bg-white p-5">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h2 className="text-[18px] font-semibold">{item.garment_type?.name}</h2>
                  <p className="text-[14px] text-ink-muted">{item.fabric_variant?.name}</p>
                </div>
                <p className="text-[18px] font-semibold tabular-nums">{item.costs.line_total.formatted}</p>
              </div>

              <dl className="mt-4 grid gap-x-8 gap-y-1 text-[14px] sm:grid-cols-2">
                <Row label={`Fabric (${item.yards.total} yd)`} value={item.costs.fabric.formatted} />
                <Row label="Sewing" value={item.costs.sewing.formatted} />
                <Row label="Options" value={item.costs.options.formatted} />
                <Row label="Quantity" value={String(item.quantity)} />
              </dl>

              {item.measurements.length > 0 ? (
                <div className="mt-5 border-t border-line pt-4">
                  <h3 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
                    Cut to these measurements
                  </h3>
                  <dl className="mt-3 grid grid-cols-2 gap-x-8 gap-y-1 text-[14px] sm:grid-cols-4">
                    {item.measurements.map((m) => (
                      <Row key={m.key} label={m.label} value={`${m.value_inches}"`} />
                    ))}
                  </dl>
                </div>
              ) : null}

              {item.style_notes ? (
                <div className="mt-5 border-t border-line pt-4">
                  <h3 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
                    Customer notes
                  </h3>
                  <p className="mt-2 text-[14px]">{item.style_notes}</p>
                </div>
              ) : null}

              {item.inspirations.length > 0 ? (
                <div className="mt-5 border-t border-line pt-4">
                  <h3 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
                    Inspiration
                  </h3>
                  <ul className="mt-3 grid grid-cols-4 gap-3 sm:grid-cols-6">
                    {item.inspirations.map((img) => (
                      <li key={img.id}>
                        <img
                          src={img.url}
                          alt={img.original_name ?? 'Customer reference image'}
                          className="aspect-square w-full rounded-lg border border-line object-cover"
                        />
                      </li>
                    ))}
                  </ul>
                </div>
              ) : null}
            </section>
          ))}

          {order.events && order.events.length > 0 ? (
            <section className="rounded-xl border border-line bg-white p-5">
              <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
                Audit trail
              </h2>
              <ol className="mt-3">
                {order.events.map((e) => (
                  <li key={e.id} className="border-b border-line py-2.5 text-[14px] last:border-0">
                    <div className="flex justify-between gap-4">
                      <span className="font-medium">{e.label}</span>
                      <span className="text-ink-muted">{shortDate(e.at)}</span>
                    </div>
                    {e.note ? <p className="mt-0.5 text-[13px] text-ink-muted">{e.note}</p> : null}
                  </li>
                ))}
              </ol>
            </section>
          ) : null}
        </div>

        <aside className="space-y-6">
          <section className="rounded-xl border border-line bg-white p-5">
            <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
              Advance the stage
            </h2>
            <p className="mt-2 text-[14px]">
              Currently <strong>{order.status_label}</strong>.
            </p>

            {allowedTransitions.length === 0 ? (
              <p className="mt-3 text-[14px] text-ink-muted">This order is finished; nothing to advance.</p>
            ) : (
              <div className="mt-3 space-y-3">
                <select
                  value={target}
                  onChange={(e) => setTarget(e.target.value)}
                  className="w-full rounded-lg border border-line px-3 py-2 text-[14px]"
                >
                  {allowedTransitions.map((t) => (
                    <option key={t.value} value={t.value}>
                      {t.label}
                    </option>
                  ))}
                </select>

                <textarea
                  value={note}
                  onChange={(e) => setNote(e.target.value)}
                  rows={2}
                  placeholder="Note for the customer (optional)"
                  className="w-full rounded-lg border border-line px-3 py-2 text-[14px]"
                />

                <button
                  type="button"
                  onClick={() =>
                    router.post(
                      `/admin/orders/${order.id}/advance`,
                      { status: target, note },
                      { preserveScroll: true, onSuccess: () => setNote('') },
                    )
                  }
                  className="w-full rounded-lg bg-ink px-4 py-2.5 text-[14px] font-medium text-cream hover:bg-[#2A2420]"
                >
                  Move to this stage
                </button>
              </div>
            )}
          </section>

          <section className="rounded-xl border border-line bg-white p-5">
            <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">Money</h2>
            <dl className="mt-3 space-y-1 text-[14px]">
              <Row label="Subtotal" value={order.totals.subtotal.formatted} />
              <Row label="Shipping" value={order.totals.shipping.formatted} />
              <Row label="Total" value={order.totals.total.formatted} />
              <Row label="Paid" value={order.totals.paid.formatted} />
              <Row label="Balance" value={order.totals.balance_due.formatted} />
              {order.totals.display_total ? (
                <Row label={`Charged (${order.currency_code})`} value={order.totals.display_total.formatted} />
              ) : null}
            </dl>
          </section>

          <section className="rounded-xl border border-line bg-white p-5">
            <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
              Assign a tailor
            </h2>
            <select
              onChange={(e) =>
                router.post(
                  `/admin/orders/${order.id}/tailor`,
                  { tailor_id: Number(e.target.value) },
                  { preserveScroll: true },
                )
              }
              className="mt-3 w-full rounded-lg border border-line px-3 py-2 text-[14px]"
              defaultValue=""
            >
              <option value="" disabled>
                Choose a tailor
              </option>
              {tailors.map((t) => (
                <option key={t.id} value={t.id}>
                  {t.name}
                </option>
              ))}
            </select>
          </section>

          {order.shipping_address ? (
            <section className="rounded-xl border border-line bg-white p-5">
              <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
                Ship to
              </h2>
              <p className="mt-2 text-[14px]">
                {Object.values(order.shipping_address).filter(Boolean).join(', ')}
              </p>
            </section>
          ) : null}
        </aside>
      </div>
    </AdminLayout>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-4">
      <dt className="text-ink-muted">{label}</dt>
      <dd className="tabular-nums">{value}</dd>
    </div>
  )
}

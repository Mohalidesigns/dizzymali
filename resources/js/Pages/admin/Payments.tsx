import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import { shortDate } from '../../lib/format'
import type { Money } from '../../types'

type PaymentRow = {
  id: number
  order_id: number
  order_reference: string | null
  customer: string | null
  gateway: string
  gateway_reference: string | null
  status: string
  kind: string
  amount: Money
  charged: Money | null
  paid_at: string | null
  created_at: string | null
}

type GatewayStatus = {
  name: string
  configured: boolean
  is_primary: boolean
  is_fallback: boolean
}

export default function Payments({
  payments,
  gatewayStatus,
  filters,
}: {
  payments: { data: PaymentRow[] }
  gatewayStatus: GatewayStatus[]
  filters: { status?: string; gateway?: string; search?: string }
}) {
  const [search, setSearch] = useState(filters.search ?? '')

  return (
    <AdminLayout title="Payments">
      <Head title="Payments" />

      {/* Being unconfigured is a normal state, shown plainly. A gateway that
          silently does nothing is how you find out at the worst moment. */}
      <section className="mb-6 rounded-xl border border-line bg-white p-5">
        <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
          Gateways
        </h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-3">
          {gatewayStatus.map((g) => (
            <div
              key={g.name}
              className={`rounded-lg border p-4 ${g.configured ? 'border-line' : 'border-terracotta/50 bg-terracotta/5'}`}
            >
              <div className="flex items-center justify-between gap-2">
                <span className="font-medium capitalize">{g.name}</span>
                <span className="text-[11px] uppercase tracking-[0.12em] text-ink-muted">
                  {g.is_primary ? 'Primary' : g.is_fallback ? 'Fallback' : ''}
                </span>
              </div>
              <p className={`mt-1 text-[13px] ${g.configured ? 'text-success' : 'text-accent-ink'}`}>
                {g.configured ? 'Ready' : 'Awaiting credentials'}
              </p>
              {!g.configured ? (
                <p className="mt-1 text-[12px] text-ink-muted">
                  Add the keys to .env — nothing else needs changing.
                </p>
              ) : null}
            </div>
          ))}
        </div>
      </section>

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <form
          onSubmit={(e) => {
            e.preventDefault()
            router.get('/admin/payments', { ...filters, search }, { preserveState: true })
          }}
        >
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Order or gateway reference"
            className="w-72 rounded-lg border border-line bg-white px-3 py-2 text-[14px] focus:border-accent-ink focus:outline-none"
          />
        </form>

        <select
          value={filters.status ?? ''}
          onChange={(e) =>
            router.get('/admin/payments', { ...filters, status: e.target.value || undefined }, { preserveState: true })
          }
          className="rounded-lg border border-line bg-white px-3 py-2 text-[14px]"
        >
          <option value="">All statuses</option>
          {['pending', 'success', 'failed', 'refunded'].map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-x-auto rounded-xl border border-line bg-white">
        <table className="w-full min-w-[900px] text-left text-[14px]">
          <thead className="border-b border-line text-[12px] uppercase tracking-[0.1em] text-ink-muted">
            <tr>
              <th className="px-4 py-3">Order</th>
              <th className="px-4 py-3">Customer</th>
              <th className="px-4 py-3">Gateway</th>
              <th className="px-4 py-3">Status</th>
              <th className="px-4 py-3 text-right">Amount</th>
              <th className="px-4 py-3 text-right">Charged</th>
              <th className="px-4 py-3">Paid</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {payments.data.map((p) => (
              <tr key={p.id} className="border-b border-line last:border-0 hover:bg-sand/40">
                <td className="px-4 py-3">
                  <Link href={`/admin/orders/${p.order_id}`} className="text-accent-ink hover:underline">
                    {p.order_reference}
                  </Link>
                  <span className="block text-[12px] text-ink-muted">{p.gateway_reference}</span>
                </td>
                <td className="px-4 py-3">{p.customer ?? '—'}</td>
                <td className="px-4 py-3 capitalize">
                  {p.gateway}
                  {p.kind === 'deposit' ? <span className="ml-2 text-[12px] text-ink-muted">deposit</span> : null}
                </td>
                <td className="px-4 py-3">
                  <StatusPill status={p.status} />
                </td>
                <td className="px-4 py-3 text-right tabular-nums">{p.amount.formatted}</td>
                <td className="px-4 py-3 text-right tabular-nums">{p.charged?.formatted ?? '—'}</td>
                <td className="px-4 py-3">{shortDate(p.paid_at)}</td>
                <td className="px-4 py-3 text-right">
                  {p.status === 'success' ? (
                    <button
                      type="button"
                      onClick={() => {
                        const reason = window.prompt('Why is this being refunded?')
                        if (reason) {
                          router.post(`/admin/payments/${p.id}/refund`, { reason }, { preserveScroll: true })
                        }
                      }}
                      className="text-[13px] text-danger hover:underline"
                    >
                      Record refund
                    </button>
                  ) : null}
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {payments.data.length === 0 ? (
          <p className="px-4 py-10 text-center text-ink-muted">No payments match those filters.</p>
        ) : null}
      </div>
    </AdminLayout>
  )
}

function StatusPill({ status }: { status: string }) {
  const tone =
    status === 'success'
      ? 'bg-success/10 text-success'
      : status === 'failed'
        ? 'bg-danger/10 text-danger'
        : status === 'refunded'
          ? 'bg-terracotta/15 text-accent-ink'
          : 'bg-sand text-ink-muted'

  return (
    <span className={`inline-block rounded-[--radius-pill] px-2.5 py-1 text-[12px] capitalize ${tone}`}>
      {status}
    </span>
  )
}

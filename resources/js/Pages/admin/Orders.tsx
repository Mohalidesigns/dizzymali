import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import { shortDate } from '../../lib/format'
import type { Order } from '../../types'

export default function Orders({
  orders,
  statuses,
  filters,
}: {
  orders: { data: Order[] }
  statuses: Array<{ value: string; label: string }>
  filters: { status?: string; search?: string }
}) {
  const [search, setSearch] = useState(filters.search ?? '')

  return (
    <AdminLayout title="Orders">
      <Head title="Orders" />

      <div className="mb-4 flex flex-wrap items-center gap-3">
        <form
          onSubmit={(e) => {
            e.preventDefault()
            router.get('/admin/orders', { ...filters, search }, { preserveState: true })
          }}
        >
          <input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="Reference, name or email"
            className="w-72 rounded-lg border border-line bg-white px-3 py-2 text-[14px] focus:border-accent-ink focus:outline-none"
          />
        </form>

        <select
          value={filters.status ?? ''}
          onChange={(e) =>
            router.get('/admin/orders', { ...filters, status: e.target.value || undefined }, { preserveState: true })
          }
          className="rounded-lg border border-line bg-white px-3 py-2 text-[14px]"
        >
          <option value="">All stages</option>
          {statuses.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
        </select>
      </div>

      <div className="overflow-x-auto rounded-xl border border-line bg-white">
        <table className="w-full min-w-[900px] text-left text-[14px]">
          <thead className="border-b border-line text-[12px] uppercase tracking-[0.1em] text-ink-muted">
            <tr>
              <th className="px-4 py-3">Reference</th>
              <th className="px-4 py-3">Garment</th>
              <th className="px-4 py-3">Stage</th>
              <th className="px-4 py-3">Promised</th>
              <th className="px-4 py-3 text-right">Total</th>
              <th className="px-4 py-3 text-right">Balance</th>
            </tr>
          </thead>
          <tbody>
            {orders.data.map((o) => (
              <tr key={o.id} className="border-b border-line last:border-0 hover:bg-sand/40">
                <td className="px-4 py-3">
                  <Link href={`/admin/orders/${o.id}`} className="font-medium text-accent-ink hover:underline">
                    {o.reference}
                  </Link>
                </td>
                <td className="px-4 py-3">
                  {o.items[0]?.garment_type?.name ?? '—'}
                  <span className="block text-[13px] text-ink-muted">
                    {o.items[0]?.fabric_variant?.name ?? ''}
                  </span>
                </td>
                <td className="px-4 py-3">{o.status_label}</td>
                <td className={`px-4 py-3 ${o.is_overdue ? 'text-danger' : ''}`}>
                  {shortDate(o.promised_at)}
                </td>
                <td className="px-4 py-3 text-right tabular-nums">{o.totals.total.formatted}</td>
                <td className="px-4 py-3 text-right tabular-nums">
                  {o.totals.balance_due.minor === 0 ? '—' : o.totals.balance_due.formatted}
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {orders.data.length === 0 ? (
          <p className="px-4 py-10 text-center text-ink-muted">No orders match those filters.</p>
        ) : null}
      </div>
    </AdminLayout>
  )
}

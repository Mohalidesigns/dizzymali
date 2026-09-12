import { Head, Link } from '@inertiajs/react'
import AdminLayout from '../../Layouts/AdminLayout'
import { shortDate } from '../../lib/format'
import type { Address, MeasurementProfile, Order } from '../../types'

export default function CustomerDetail({
  customer,
  profiles,
  orders,
  addresses,
}: {
  customer: {
    id: number
    name: string
    email: string
    phone: string | null
    country_code: string | null
    preferred_currency: string
    created_at: string | null
  }
  profiles: { data: MeasurementProfile[] }
  orders: { data: Order[] }
  addresses: Address[]
}) {
  return (
    <AdminLayout title={customer.name}>
      <Head title={customer.name} />

      <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div className="space-y-6">
          <section className="rounded-xl border border-line bg-white p-5">
            <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
              Orders
            </h2>
            <table className="mt-3 w-full text-left text-[14px]">
              <tbody>
                {orders.data.map((o) => (
                  <tr key={o.id} className="border-b border-line last:border-0">
                    <td className="py-2">
                      <Link href={`/admin/orders/${o.id}`} className="text-accent-ink hover:underline">
                        {o.reference}
                      </Link>
                    </td>
                    <td className="py-2">{o.items[0]?.garment_type?.name ?? '—'}</td>
                    <td className="py-2 text-ink-muted">{o.status_label}</td>
                    <td className="py-2 text-right tabular-nums">{o.totals.total.formatted}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {orders.data.length === 0 ? (
              <p className="mt-2 text-[14px] text-ink-muted">No orders yet.</p>
            ) : null}
          </section>

          {profiles.data.map((p) => (
            <section key={p.id} className="rounded-xl border border-line bg-white p-5">
              <div className="flex items-center justify-between">
                <h2 className="text-[16px] font-semibold">{p.name}</h2>
                <span className="text-[13px] text-ink-muted">
                  Updated {shortDate(p.updated_at)}
                </span>
              </div>
              <dl className="mt-3 grid grid-cols-2 gap-x-8 gap-y-1 text-[14px] sm:grid-cols-4">
                {(p.values ?? []).map((v) => (
                  <div key={v.key} className="flex justify-between">
                    <dt className="text-ink-muted">{v.label}</dt>
                    <dd className="tabular-nums">{v.inches}&quot;</dd>
                  </div>
                ))}
              </dl>
            </section>
          ))}
        </div>

        <aside className="space-y-6">
          <section className="rounded-xl border border-line bg-white p-5">
            <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
              Details
            </h2>
            <dl className="mt-3 space-y-1 text-[14px]">
              <Row label="Email" value={customer.email} />
              <Row label="Phone" value={customer.phone ?? '—'} />
              <Row label="Country" value={customer.country_code ?? '—'} />
              <Row label="Currency" value={customer.preferred_currency} />
              <Row label="Joined" value={customer.created_at ?? '—'} />
            </dl>
          </section>

          {addresses.length > 0 ? (
            <section className="rounded-xl border border-line bg-white p-5">
              <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
                Addresses
              </h2>
              <ul className="mt-3 space-y-3 text-[14px]">
                {addresses.map((a) => (
                  <li key={a.id} className="border-b border-line pb-2 last:border-0">
                    <p className="font-medium">{a.label ?? a.recipient_name}</p>
                    <p className="text-ink-muted">
                      {[a.line_1, a.city, a.postcode, a.country_code].filter(Boolean).join(', ')}
                    </p>
                  </li>
                ))}
              </ul>
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
      <dd>{value}</dd>
    </div>
  )
}

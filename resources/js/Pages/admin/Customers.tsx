import { Head, Link, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import type { Money } from '../../types'

type Customer = {
  id: number
  name: string
  email: string
  country_code: string | null
  orders_count: number
  lifetime_value: Money
  created_at: string | null
}

export default function Customers({
  customers,
  filters,
}: {
  customers: { data: Customer[] }
  filters: { search?: string }
}) {
  const [search, setSearch] = useState(filters.search ?? '')

  return (
    <AdminLayout title="Customers">
      <Head title="Customers" />

      <form
        className="mb-4"
        onSubmit={(e) => {
          e.preventDefault()
          router.get('/admin/customers', { search }, { preserveState: true })
        }}
      >
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Name or email"
          className="w-72 rounded-lg border border-line bg-white px-3 py-2 text-[14px] focus:border-accent-ink focus:outline-none"
        />
      </form>

      <div className="overflow-x-auto rounded-xl border border-line bg-white">
        <table className="w-full min-w-[760px] text-left text-[14px]">
          <thead className="border-b border-line text-[12px] uppercase tracking-[0.1em] text-ink-muted">
            <tr>
              <th className="px-4 py-3">Name</th>
              <th className="px-4 py-3">Email</th>
              <th className="px-4 py-3">Country</th>
              <th className="px-4 py-3 text-right">Orders</th>
              <th className="px-4 py-3 text-right">Lifetime value</th>
            </tr>
          </thead>
          <tbody>
            {customers.data.map((c) => (
              <tr key={c.id} className="border-b border-line last:border-0 hover:bg-sand/40">
                <td className="px-4 py-3">
                  <Link href={`/admin/customers/${c.id}`} className="text-accent-ink hover:underline">
                    {c.name}
                  </Link>
                </td>
                <td className="px-4 py-3 text-ink-muted">{c.email}</td>
                <td className="px-4 py-3">{c.country_code ?? '—'}</td>
                <td className="px-4 py-3 text-right tabular-nums">{c.orders_count}</td>
                <td className="px-4 py-3 text-right tabular-nums">{c.lifetime_value.formatted}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  )
}

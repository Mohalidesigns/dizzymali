import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import type { Money } from '../../types'

type Rate = {
  id: number
  service_level: string
  min_grams: number
  max_grams: number
  price: Money
  transit_days_min: number
  transit_days_max: number
  is_active: boolean
}

type Zone = {
  id: number
  slug: string
  name: string
  country_codes: string[] | null
  is_default: boolean
  is_active: boolean
  rates: Rate[]
}

export default function Shipping({ zones, carriers }: { zones: Zone[]; carriers: string[] }) {
  return (
    <AdminLayout title="Shipping">
      <Head title="Shipping" />

      <p className="mb-6 max-w-2xl text-[14px] text-ink-muted">
        Garment weight is estimated from the garment type plus the yards used, then priced against
        these bands. Carriers supported for tracking links: {carriers.join(', ')}.
      </p>

      <div className="space-y-6">
        {zones.map((zone) => (
          <ZoneCard key={zone.id} zone={zone} />
        ))}
      </div>
    </AdminLayout>
  )
}

function ZoneCard({ zone }: { zone: Zone }) {
  const [draft, setDraft] = useState({
    service_level: 'standard',
    min_grams: '0',
    max_grams: '6000',
    price_naira: '',
    transit_days_min: '7',
    transit_days_max: '14',
  })

  return (
    <section className="rounded-xl border border-line bg-white p-5">
      <div className="flex flex-wrap items-baseline justify-between gap-3">
        <div>
          <h2 className="text-[16px] font-semibold">{zone.name}</h2>
          <p className="text-[13px] text-ink-muted">
            {zone.is_default ? 'Default zone — anything not matched elsewhere' : (zone.country_codes ?? []).join(', ')}
          </p>
        </div>
      </div>

      <table className="mt-4 w-full text-left text-[14px]">
        <thead className="border-b border-line text-[12px] uppercase tracking-[0.1em] text-ink-muted">
          <tr>
            <th className="py-2">Service</th>
            <th className="py-2">Weight band</th>
            <th className="py-2 text-right">Price</th>
            <th className="py-2">Transit</th>
            <th className="py-2" />
          </tr>
        </thead>
        <tbody>
          {zone.rates.map((rate) => (
            <tr key={rate.id} className="border-b border-line last:border-0">
              <td className="py-2 capitalize">{rate.service_level}</td>
              <td className="py-2 tabular-nums">
                {rate.min_grams}–{rate.max_grams} g
              </td>
              <td className="py-2 text-right tabular-nums">{rate.price.formatted}</td>
              <td className="py-2 tabular-nums">
                {rate.transit_days_min}–{rate.transit_days_max} days
              </td>
              <td className="py-2 text-right">
                <button
                  type="button"
                  onClick={() => router.delete(`/admin/shipping/rates/${rate.id}`, { preserveScroll: true })}
                  className="text-[13px] text-danger hover:underline"
                >
                  Remove
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>

      <div className="mt-4 flex flex-wrap items-end gap-3 border-t border-line pt-4">
        <label className="text-[12px] text-ink-muted">
          Service
          <select
            value={draft.service_level}
            onChange={(e) => setDraft((d) => ({ ...d, service_level: e.target.value }))}
            className="mt-1 block rounded-lg border border-line px-3 py-2 text-[14px] text-ink"
          >
            <option value="standard">Standard</option>
            <option value="express">Express</option>
          </select>
        </label>

        {([
          ['min_grams', 'From (g)'],
          ['max_grams', 'To (g)'],
          ['price_naira', 'Price (₦)'],
          ['transit_days_min', 'Days min'],
          ['transit_days_max', 'Days max'],
        ] as const).map(([key, label]) => (
          <label key={key} className="text-[12px] text-ink-muted">
            {label}
            <input
              type="number"
              value={draft[key]}
              onChange={(e) => setDraft((d) => ({ ...d, [key]: e.target.value }))}
              className="mt-1 block w-28 rounded-lg border border-line px-3 py-2 text-right text-[14px] tabular-nums text-ink"
            />
          </label>
        ))}

        <button
          type="button"
          disabled={!draft.price_naira}
          onClick={() =>
            router.post(
              `/admin/shipping/zones/${zone.id}/rates`,
              Object.fromEntries(Object.entries(draft).map(([k, v]) => [k, k === 'service_level' ? v : Number(v)])),
              { preserveScroll: true, onSuccess: () => setDraft((d) => ({ ...d, price_naira: '' })) },
            )
          }
          className="rounded-lg border border-line px-4 py-2 text-[14px] disabled:opacity-40"
        >
          Add rate
        </button>
      </div>
    </section>
  )
}

import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'

type Currency = {
  id: number
  code: string
  name: string
  symbol: string
  decimals: number
  rounding_minor: number
  is_base: boolean
  is_active: boolean
  rate: {
    rate: number
    margin_percent: number
    source: string
    effective_at: string | null
    naira_per_unit: number | null
  } | null
}

export default function Currencies({
  currencies,
  provider,
}: {
  currencies: Currency[]
  provider: { driver: string; configured: boolean }
}) {
  return (
    <AdminLayout title="Currencies">
      <Head title="Currencies" />

      <div className="mb-6 rounded-xl border border-line bg-white p-5">
        <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
          Rate source
        </h2>
        <p className="mt-2 text-[14px]">
          {provider.driver === 'manual' || !provider.configured ? (
            <>
              Rates are set by hand here. To refresh them daily, set <code>FX_DRIVER</code> and{' '}
              <code>FX_ENDPOINT</code> and schedule <code>php artisan fx:refresh</code>.
            </>
          ) : (
            <>
              Refreshed daily from <strong>{provider.driver}</strong>. Your margin carries forward
              across refreshes.
            </>
          )}
        </p>
        <p className="mt-2 text-[13px] text-ink-muted">
          Changing a rate never affects an order already placed — the rate is frozen onto the order at
          submission.
        </p>
      </div>

      <div className="space-y-4">
        {currencies.map((c) => (
          <CurrencyCard key={c.id} currency={c} />
        ))}
      </div>
    </AdminLayout>
  )
}

function CurrencyCard({ currency }: { currency: Currency }) {
  const [nairaPerUnit, setNairaPerUnit] = useState(String(currency.rate?.naira_per_unit ?? ''))
  const [margin, setMargin] = useState(String(currency.rate?.margin_percent ?? 8))

  return (
    <section className="rounded-xl border border-line bg-white p-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="text-[16px] font-semibold">
            {currency.symbol} {currency.code}
            {currency.is_base ? <span className="ml-2 text-[12px] text-ink-muted">base</span> : null}
          </h2>
          <p className="text-[13px] text-ink-muted">
            {currency.rate
              ? `1 ${currency.code} = ₦${currency.rate.naira_per_unit?.toLocaleString()} · ${currency.rate.margin_percent}% margin · ${currency.rate.source}`
              : 'No rate set'}
          </p>
        </div>

        {currency.is_base ? null : (
          <div className="flex flex-wrap items-end gap-3">
            <label className="text-[12px] text-ink-muted">
              ₦ per 1 {currency.code}
              <input
                type="number"
                step="0.01"
                value={nairaPerUnit}
                onChange={(e) => setNairaPerUnit(e.target.value)}
                className="mt-1 block w-32 rounded-lg border border-line px-3 py-2 text-right text-[14px] tabular-nums text-ink"
              />
            </label>
            <label className="text-[12px] text-ink-muted">
              Margin %
              <input
                type="number"
                step="0.5"
                value={margin}
                onChange={(e) => setMargin(e.target.value)}
                className="mt-1 block w-24 rounded-lg border border-line px-3 py-2 text-right text-[14px] tabular-nums text-ink"
              />
            </label>
            <button
              type="button"
              disabled={!nairaPerUnit}
              onClick={() =>
                router.post(
                  `/admin/currencies/${currency.id}/rates`,
                  { naira_per_unit: Number(nairaPerUnit), margin_percent: Number(margin) },
                  { preserveScroll: true },
                )
              }
              className="rounded-lg bg-ink px-4 py-2 text-[14px] font-medium text-cream hover:bg-[#2A2420] disabled:opacity-40"
            >
              Save rate
            </button>
          </div>
        )}
      </div>
    </section>
  )
}

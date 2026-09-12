import { useMemo, useState } from 'react'
import { Badge, Card, Eyebrow, Swatch } from '../Ui'
import { cx } from '../../lib/format'
import type { FabricVariant } from '../../types'

export default function StepFabric({
  variants,
  materials,
  selectedId,
  onSelect,
}: {
  variants: FabricVariant[]
  materials: Array<{ id: number; slug: string; name: string }>
  selectedId: number | null
  onSelect: (id: number) => void
}) {
  const [material, setMaterial] = useState<string | null>(null)
  const [inStockOnly, setInStockOnly] = useState(false)
  const [detail, setDetail] = useState<FabricVariant | null>(null)

  const filtered = useMemo(
    () =>
      variants.filter((v) => {
        if (material && v.fabric?.material?.slug !== material) return false
        if (inStockOnly && v.available_yards <= 0) return false
        return true
      }),
    [variants, material, inStockOnly],
  )

  return (
    <div>
      <Eyebrow>Step 2 of 6</Eyebrow>
      <h2 className="mt-3 font-display text-4xl">Choose your cloth</h2>
      <p className="mt-2 max-w-xl text-ink-muted">
        Priced by the yard. How many yards your garment needs is worked out from your measurements in
        the next step, and shown before you pay.
      </p>

      <div className="mt-6 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={() => setMaterial(null)}
          className={cx(
            'rounded-[--radius-pill] border px-4 py-1.5 font-ui text-[12px] uppercase tracking-[0.14em]',
            material === null ? 'border-ink bg-ink text-cream' : 'border-line text-ink-muted hover:text-ink',
          )}
        >
          All
        </button>
        {materials.map((m) => (
          <button
            key={m.id}
            type="button"
            onClick={() => setMaterial(m.slug)}
            className={cx(
              'rounded-[--radius-pill] border px-4 py-1.5 font-ui text-[12px] uppercase tracking-[0.14em]',
              material === m.slug ? 'border-ink bg-ink text-cream' : 'border-line text-ink-muted hover:text-ink',
            )}
          >
            {m.name}
          </button>
        ))}
        <label className="ml-2 flex items-center gap-2 text-[13px] text-ink-muted">
          <input type="checkbox" checked={inStockOnly} onChange={(e) => setInStockOnly(e.target.checked)} />
          In stock only
        </label>
      </div>

      <div className="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        {filtered.map((v) => (
          <Card key={v.id} interactive selected={selectedId === v.id} className="p-0 overflow-hidden">
            <button type="button" onClick={() => onSelect(v.id)} className="block w-full text-left">
              <div className="h-40 w-full" style={{ backgroundColor: v.colour_hex ?? '#EFE7DC' }} />
              <div className="p-5">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <h3 className="font-display text-xl leading-tight">{v.fabric?.name}</h3>
                    <p className="text-[13px] text-ink-muted">
                      {v.colour_name}
                      {v.pattern ? ` · ${v.pattern}` : ''}
                    </p>
                  </div>
                  <Swatch hex={v.colour_hex} size={28} />
                </div>

                <p className="tabular mt-3 text-[15px]">{v.price_per_yard.formatted} / yard</p>

                <div className="mt-3 flex items-center gap-2">
                  {v.available_yards <= 0 ? (
                    <Badge tone="danger">Out of stock</Badge>
                  ) : v.is_low_stock ? (
                    <Badge tone="warn">Only {v.available_yards} yd left</Badge>
                  ) : (
                    <Badge tone="success">In stock</Badge>
                  )}
                  {v.fabric?.material ? <Badge>{v.fabric.material.name}</Badge> : null}
                </div>
              </div>
            </button>
            <div className="border-t border-line px-5 py-2.5">
              <button
                type="button"
                onClick={() => setDetail(v)}
                className="font-ui text-[12px] uppercase tracking-[0.14em] text-accent-ink hover:underline"
              >
                Fabric details
              </button>
            </div>
          </Card>
        ))}
      </div>

      {filtered.length === 0 ? (
        <p className="mt-8 text-ink-muted">Nothing matches those filters. Try widening them.</p>
      ) : null}

      {detail ? (
        <div
          role="dialog"
          aria-modal="true"
          aria-label={`${detail.fabric?.name} details`}
          className="fixed inset-0 z-50 flex items-end justify-center bg-ink/40 p-0 sm:items-center sm:p-6"
          onClick={() => setDetail(null)}
        >
          <div
            className="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-t-[--radius-card] bg-white p-6 sm:rounded-[--radius-card]"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <h3 className="font-display text-2xl">{detail.fabric?.name}</h3>
                <p className="text-[13px] text-ink-muted">{detail.colour_name}</p>
              </div>
              <button type="button" onClick={() => setDetail(null)} className="text-ink-muted hover:text-ink" aria-label="Close">
                ✕
              </button>
            </div>

            <div className="mt-4 h-28 rounded-[--radius-input]" style={{ backgroundColor: detail.colour_hex ?? '#EFE7DC' }} />

            <dl className="mt-5 space-y-3 text-[14px]">
              {[
                ['Material', detail.fabric?.material?.name],
                ['Origin', detail.fabric?.origin],
                ['Weight', detail.fabric?.gsm ? `${detail.fabric.gsm} gsm` : null],
                ['Width', detail.fabric?.width_inches ? `${detail.fabric.width_inches}"` : null],
                ['Drape', detail.fabric?.drape_notes],
                ['Care', detail.fabric?.care_instructions],
              ]
                .filter(([, value]) => Boolean(value))
                .map(([label, value]) => (
                  <div key={label as string} className="grid grid-cols-[100px_1fr] gap-3">
                    <dt className="eyebrow text-ink">{label}</dt>
                    <dd className="text-ink-muted">{value}</dd>
                  </div>
                ))}
            </dl>
          </div>
        </div>
      ) : null}
    </div>
  )
}

import { router } from '@inertiajs/react'
import Media from '../../Components/Media'
import Seo, { type SeoData } from '../../Components/Seo'
import { Badge, Card, PageTitle, Swatch } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import { cx } from '../../lib/format'
import type { FabricVariant } from '../../types'

export default function Fabrics({
  variants,
  materials,
  filters,
  seo,
}: {
  variants: { data: FabricVariant[]; links?: unknown }
  materials: Array<{ id: number; slug: string; name: string; description: string | null }>
  filters: { material?: string; in_stock?: string }
  seo?: SeoData
}) {
  const apply = (patch: Record<string, unknown>) =>
    router.get('/fabrics', { ...filters, ...patch }, { preserveScroll: true, preserveState: true })

  return (
    <StorefrontLayout>
      <Seo seo={seo} />
      <PageTitle sub="Priced by the yard. How many yards your garment needs depends on the cut and on your measurements.">
        Fabrics
      </PageTitle>

      <div className="mb-8 flex flex-wrap items-center gap-2">
        <button
          type="button"
          onClick={() => apply({ material: undefined })}
          className={cx(
            'rounded-[--radius-pill] border px-4 py-1.5 font-ui text-[12px] uppercase tracking-[0.14em]',
            !filters.material ? 'border-ink bg-ink text-cream' : 'border-line text-ink-muted hover:text-ink',
          )}
        >
          All materials
        </button>
        {materials.map((m) => (
          <button
            key={m.id}
            type="button"
            onClick={() => apply({ material: m.slug })}
            className={cx(
              'rounded-[--radius-pill] border px-4 py-1.5 font-ui text-[12px] uppercase tracking-[0.14em]',
              filters.material === m.slug ? 'border-ink bg-ink text-cream' : 'border-line text-ink-muted hover:text-ink',
            )}
          >
            {m.name}
          </button>
        ))}
      </div>

      <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        {variants.data.map((v) => (
          <Card key={v.id} className="overflow-hidden p-0">
            <Media image={v.image} ratio="aspect-[3/2]" sizes="(max-width: 640px) 100vw, 33vw" />
            <div className="p-5">
              <div className="flex items-start justify-between gap-3">
                <div>
                  <h2 className="font-display text-xl leading-tight">{v.fabric?.name}</h2>
                  <p className="text-[13px] text-ink-muted">
                    {v.colour_name}
                    {v.pattern ? ` · ${v.pattern}` : ''}
                  </p>
                </div>
                <Swatch hex={v.colour_hex} size={28} />
              </div>

              <p className="tabular mt-3 text-[16px]">{v.price_per_yard.formatted} / yard</p>

              <dl className="mt-3 space-y-1 text-[13px] text-ink-muted">
                {v.fabric?.gsm ? (
                  <div className="flex justify-between">
                    <dt>Weight</dt>
                    <dd className="tabular">{v.fabric.gsm} gsm</dd>
                  </div>
                ) : null}
                {v.fabric?.origin ? (
                  <div className="flex justify-between">
                    <dt>Origin</dt>
                    <dd>{v.fabric.origin}</dd>
                  </div>
                ) : null}
              </dl>

              <div className="mt-4 flex flex-wrap gap-2">
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
          </Card>
        ))}
      </div>
    </StorefrontLayout>
  )
}

import { Head, Link } from '@inertiajs/react'
import { Card, PageTitle } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import type { GarmentType } from '../../types'

export default function Garments({ garmentTypes }: { garmentTypes: { data: GarmentType[] } }) {
  return (
    <StorefrontLayout>
      <Head title="Garments" />
      <PageTitle sub="Four cuts, each made to your measurements. Choose by the occasion, not by size.">
        Garments
      </PageTitle>

      <div className="grid gap-6 sm:grid-cols-2">
        {garmentTypes.data.map((g) => (
          <Link key={g.id} href={`/garments/${g.slug}`}>
            <Card interactive className="flex h-full flex-col">
              <div className="flex items-baseline justify-between gap-4">
                <h2 className="font-display text-3xl">{g.name}</h2>
                <span className="tabular text-[13px] text-accent-ink">from {g.sewing_cost.formatted}</span>
              </div>
              <p className="mt-2 text-[15px] text-ink-muted">{g.tagline}</p>
              <p className="mt-3 flex-1 text-[15px] leading-relaxed text-ink-muted">{g.description}</p>
              <dl className="tabular mt-6 flex gap-8 border-t border-line pt-4 text-[13px]">
                <div>
                  <dt className="text-ink-muted">Typical cloth</dt>
                  <dd>{g.default_yardage} yd</dd>
                </div>
                <div>
                  <dt className="text-ink-muted">Lead time</dt>
                  <dd>{g.lead_time_days} days</dd>
                </div>
                <div>
                  <dt className="text-ink-muted">Measurements</dt>
                  <dd>{g.measurement_fields?.length ?? 0} needed</dd>
                </div>
              </dl>
            </Card>
          </Link>
        ))}
      </div>
    </StorefrontLayout>
  )
}

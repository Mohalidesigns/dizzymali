import { Head } from '@inertiajs/react'
import { ButtonLink, Card, Eyebrow } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import type { GarmentType } from '../../types'

export default function Garment({ garmentType: raw }: { garmentType: { data: GarmentType } | GarmentType }) {
  const g = 'data' in raw ? raw.data : raw
  const top = (g.measurement_fields ?? []).filter((f) => f.group === 'top')
  const trouser = (g.measurement_fields ?? []).filter((f) => f.group === 'trouser')

  return (
    <StorefrontLayout>
      <Head title={g.name} />

      <div className="grid gap-12 lg:grid-cols-[1.2fr_0.8fr]">
        <div>
          <Eyebrow>Made to measure</Eyebrow>
          <h1 className="mt-3 font-display text-[clamp(2.4rem,6vw,4.2rem)]">{g.name}</h1>
          <p className="mt-3 text-[18px] text-ink-muted">{g.tagline}</p>
          <p className="mt-6 max-w-xl text-[16px] leading-relaxed">{g.description}</p>

          <div className="mt-10">
            <h2 className="eyebrow text-ink">What we measure</h2>
            <div className="mt-4 grid gap-8 sm:grid-cols-2">
              {[['Top', top], ['Trouser', trouser]].map(([label, fields]) =>
                (fields as typeof top).length === 0 ? null : (
                  <div key={label as string}>
                    <h3 className="font-display text-xl">{label as string}</h3>
                    <ul className="mt-3 space-y-2">
                      {(fields as typeof top).map((f) => (
                        <li key={f.key} className="border-b border-line pb-2">
                          <p className="text-[15px]">{f.label}</p>
                          {f.help_text ? (
                            <p className="mt-0.5 text-[13px] text-ink-muted">{f.help_text}</p>
                          ) : null}
                        </li>
                      ))}
                    </ul>
                  </div>
                ),
              )}
            </div>
          </div>

          {(g.option_groups ?? []).length > 0 ? (
            <div className="mt-12">
              <h2 className="eyebrow text-ink">Options</h2>
              <div className="mt-4 space-y-6">
                {(g.option_groups ?? []).map((group) => (
                  <div key={group.id}>
                    <h3 className="font-display text-xl">{group.name}</h3>
                    <ul className="tabular mt-2 space-y-1 text-[14px]">
                      {group.options.map((o) => (
                        <li key={o.id} className="flex justify-between border-b border-line pb-1">
                          <span>{o.name}</span>
                          <span className="text-accent-ink">
                            {o.surcharge.minor === 0 ? 'Included' : `+ ${o.surcharge.formatted}`}
                          </span>
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            </div>
          ) : null}
        </div>

        <aside>
          <Card className="sticky top-24">
            <dl className="tabular space-y-3 text-[15px]">
              <div className="flex justify-between border-b border-line pb-2">
                <dt className="text-ink-muted">Sewing from</dt>
                <dd>{g.sewing_cost.formatted}</dd>
              </div>
              <div className="flex justify-between border-b border-line pb-2">
                <dt className="text-ink-muted">Typical cloth</dt>
                <dd>{g.default_yardage} yd</dd>
              </div>
              <div className="flex justify-between border-b border-line pb-2">
                <dt className="text-ink-muted">Lead time</dt>
                <dd>{g.lead_time_days} days</dd>
              </div>
            </dl>
            <p className="mt-4 text-[13px] text-ink-muted">
              Fabric is priced separately, by the yard. Larger sizes need more cloth, and you will see
              exactly how much before you pay.
            </p>
            <ButtonLink href="/order" variant="accent" className="mt-6 w-full">
              Commission this
            </ButtonLink>
          </Card>
        </aside>
      </div>
    </StorefrontLayout>
  )
}

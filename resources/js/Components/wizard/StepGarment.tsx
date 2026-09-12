import { Card, Eyebrow } from '../Ui'
import type { GarmentType } from '../../types'

export default function StepGarment({
  garmentTypes,
  selectedId,
  onSelect,
}: {
  garmentTypes: GarmentType[]
  selectedId: number | null
  onSelect: (id: number) => void
}) {
  return (
    <div>
      <Eyebrow>Step 1 of 6</Eyebrow>
      <h2 className="mt-3 font-display text-4xl">What are we making?</h2>
      <p className="mt-2 max-w-xl text-ink-muted">
        Each garment takes a different amount of cloth and a different amount of labour, so the price
        follows the garment rather than a flat rate.
      </p>

      <div
        role="radiogroup"
        aria-label="Garment type"
        className="mt-8 grid gap-5 sm:grid-cols-2"
      >
        {garmentTypes.map((g) => (
          <Card
            key={g.id}
            interactive
            selected={selectedId === g.id}
            className="flex flex-col"
          >
            <button
              type="button"
              role="radio"
              aria-checked={selectedId === g.id}
              onClick={() => onSelect(g.id)}
              className="flex h-full flex-col text-left"
            >
              <div className="flex items-baseline justify-between gap-4">
                <h3 className="font-display text-3xl">{g.name}</h3>
                <span className="tabular font-ui text-[13px] text-accent-ink">
                  from {g.sewing_cost.formatted}
                </span>
              </div>
              <p className="mt-2 text-[14px] text-ink-muted">{g.tagline}</p>
              <p className="mt-3 flex-1 text-[14px] leading-relaxed text-ink-muted">{g.description}</p>
              <dl className="mt-5 flex gap-6 border-t border-line pt-4 text-[13px]">
                <div>
                  <dt className="text-ink-muted">Cloth</dt>
                  <dd className="tabular">{g.default_yardage} yd typical</dd>
                </div>
                <div>
                  <dt className="text-ink-muted">Ready in</dt>
                  <dd className="tabular">{g.lead_time_days} days</dd>
                </div>
              </dl>
            </button>
          </Card>
        ))}
      </div>
    </div>
  )
}

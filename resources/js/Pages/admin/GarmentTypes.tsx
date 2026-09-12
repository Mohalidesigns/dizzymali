import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import type { GarmentType, MeasurementField } from '../../types'

type Rule = {
  id: number
  garment_type_id: number
  threshold_inches: string
  additional_yards: string
  measurement_field: { id: number; key: string; label: string } | null
}

export default function GarmentTypes({
  garmentTypes,
  yardageRules,
  measurementFields,
}: {
  garmentTypes: { data: GarmentType[] }
  yardageRules: Record<string, Rule[]>
  measurementFields: MeasurementField[]
}) {
  return (
    <AdminLayout title="Garments & yardage">
      <Head title="Garments" />

      <p className="mb-6 max-w-2xl text-[14px] text-ink-muted">
        A yardage rule adds cloth when a measurement passes a threshold. Where several rules exist for
        the same measurement, only the highest one the customer clears is applied; rules on different
        measurements add together.
      </p>

      <div className="space-y-6">
        {garmentTypes.data.map((g) => (
          <GarmentCard
            key={g.id}
            garment={g}
            rules={yardageRules[String(g.id)] ?? []}
            fields={measurementFields}
          />
        ))}
      </div>
    </AdminLayout>
  )
}

function GarmentCard({
  garment,
  rules,
  fields,
}: {
  garment: GarmentType
  rules: Rule[]
  fields: MeasurementField[]
}) {
  const [sewing, setSewing] = useState(String(Math.round(garment.sewing_cost.minor / 100)))
  const [yardage, setYardage] = useState(String(garment.default_yardage))
  const [lead, setLead] = useState(String(garment.lead_time_days))
  const [newRule, setNewRule] = useState({ field: '', threshold: '', yards: '' })

  return (
    <section className="rounded-xl border border-line bg-white p-5">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="text-[18px] font-semibold">{garment.name}</h2>
          <p className="text-[13px] text-ink-muted">{garment.tagline}</p>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <LabelledInput label="Sewing (₦)" value={sewing} onChange={setSewing} />
          <LabelledInput label="Base yardage" value={yardage} onChange={setYardage} step="0.25" />
          <LabelledInput label="Lead days" value={lead} onChange={setLead} />
          <button
            type="button"
            onClick={() =>
              router.patch(
                `/admin/garment-types/${garment.id}`,
                {
                  base_sewing_cost_naira: Number(sewing),
                  default_yardage: Number(yardage),
                  lead_time_days: Number(lead),
                },
                { preserveScroll: true },
              )
            }
            className="rounded-lg bg-ink px-4 py-2 text-[14px] font-medium text-cream hover:bg-[#2A2420]"
          >
            Save
          </button>
        </div>
      </div>

      <div className="mt-5 border-t border-line pt-4">
        <h3 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
          Yardage rules
        </h3>

        {rules.length === 0 ? (
          <p className="mt-2 text-[14px] text-ink-muted">
            No rules. Every size uses the base yardage, which loses money on large orders.
          </p>
        ) : (
          <ul className="mt-2">
            {rules.map((r) => (
              <li
                key={r.id}
                className="flex items-center justify-between gap-4 border-b border-line py-2 text-[14px] last:border-0"
              >
                <span>
                  {r.measurement_field?.label} over{' '}
                  <span className="tabular-nums">{Number(r.threshold_inches)}&quot;</span> adds{' '}
                  <span className="tabular-nums">{Number(r.additional_yards)} yd</span>
                </span>
                <button
                  type="button"
                  onClick={() =>
                    router.delete(`/admin/garment-types/${garment.id}/yardage-rules/${r.id}`, {
                      preserveScroll: true,
                    })
                  }
                  className="text-[13px] text-danger hover:underline"
                >
                  Remove
                </button>
              </li>
            ))}
          </ul>
        )}

        <div className="mt-4 flex flex-wrap items-end gap-3">
          <label className="text-[12px] text-ink-muted">
            Measurement
            <select
              value={newRule.field}
              onChange={(e) => setNewRule((r) => ({ ...r, field: e.target.value }))}
              className="mt-1 block rounded-lg border border-line px-3 py-2 text-[14px] text-ink"
            >
              <option value="">Choose…</option>
              {fields.map((f) => (
                <option key={f.id} value={f.id}>
                  {f.label}
                </option>
              ))}
            </select>
          </label>
          <LabelledInput
            label="Over (inches)"
            value={newRule.threshold}
            onChange={(v) => setNewRule((r) => ({ ...r, threshold: v }))}
            step="0.5"
          />
          <LabelledInput
            label="Adds (yards)"
            value={newRule.yards}
            onChange={(v) => setNewRule((r) => ({ ...r, yards: v }))}
            step="0.25"
          />
          <button
            type="button"
            disabled={!newRule.field || !newRule.threshold || !newRule.yards}
            onClick={() =>
              router.post(
                `/admin/garment-types/${garment.id}/yardage-rules`,
                {
                  measurement_field_id: Number(newRule.field),
                  threshold_inches: Number(newRule.threshold),
                  additional_yards: Number(newRule.yards),
                },
                { preserveScroll: true, onSuccess: () => setNewRule({ field: '', threshold: '', yards: '' }) },
              )
            }
            className="rounded-lg border border-line px-4 py-2 text-[14px] disabled:opacity-40"
          >
            Add rule
          </button>
        </div>
      </div>
    </section>
  )
}

function LabelledInput({
  label,
  value,
  onChange,
  step,
}: {
  label: string
  value: string
  onChange: (value: string) => void
  step?: string
}) {
  return (
    <label className="text-[12px] text-ink-muted">
      {label}
      <input
        type="number"
        step={step}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="mt-1 block w-32 rounded-lg border border-line px-3 py-2 text-right text-[14px] tabular-nums text-ink"
      />
    </label>
  )
}

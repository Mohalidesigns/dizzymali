import { Head } from '@inertiajs/react'
import { router } from '@inertiajs/react'
import { useState } from 'react'
import { Badge, Button, Card, EmptyState, Field, Input, PageTitle } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import { cx, inchesToCm } from '../../lib/format'
import type { MeasurementField, MeasurementProfile } from '../../types'

export default function Measurements({
  profiles,
  fields,
  unitPreference,
}: {
  profiles: { data: MeasurementProfile[] }
  fields: { data: MeasurementField[] }
  unitPreference: 'in' | 'cm'
}) {
  const [unit, setUnit] = useState<'in' | 'cm'>(unitPreference)
  const [adding, setAdding] = useState(false)
  const [name, setName] = useState('')
  const [values, setValues] = useState<Record<string, string>>({})
  const [errors, setErrors] = useState<Record<string, string>>({})

  const top = fields.data.filter((f) => f.group === 'top')
  const trouser = fields.data.filter((f) => f.group === 'trouser')

  return (
    <StorefrontLayout>
      <Head title="My measurements" />

      <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <PageTitle sub="Saved once, reused on every order. Stored in inches and shown in whichever unit you prefer.">
          My measurements
        </PageTitle>
        <div className="flex items-center gap-1 rounded-[--radius-pill] border border-line p-1">
          {(['in', 'cm'] as const).map((u) => (
            <button
              key={u}
              type="button"
              onClick={() => setUnit(u)}
              aria-pressed={unit === u}
              className={cx(
                'rounded-[--radius-pill] px-3 py-1 font-ui text-[12px] uppercase tracking-[0.14em]',
                unit === u ? 'bg-sand text-ink' : 'text-ink-muted',
              )}
            >
              {u}
            </button>
          ))}
        </div>
      </div>

      {profiles.data.length === 0 && !adding ? (
        <EmptyState
          title="No measurements saved"
          body="Take them once and every future order becomes a single tap."
          action={<Button onClick={() => setAdding(true)}>Add measurements</Button>}
        />
      ) : (
        <div className="grid gap-5 md:grid-cols-2">
          {profiles.data.map((p) => (
            <Card key={p.id}>
              <div className="flex items-start justify-between gap-3">
                <h2 className="font-display text-2xl">{p.name}</h2>
                <div className="flex gap-2">
                  {p.is_default ? <Badge tone="gold">Default</Badge> : null}
                  {p.review_status === 'pending_review' ? <Badge tone="warn">In review</Badge> : null}
                </div>
              </div>

              <dl className="tabular mt-4 grid grid-cols-2 gap-x-6 gap-y-1 text-[14px]">
                {(p.values ?? []).map((v) => (
                  <div key={v.key} className="flex justify-between border-b border-line pb-1">
                    <dt className="text-ink-muted">{v.label}</dt>
                    <dd>{unit === 'cm' ? `${v.cm} cm` : `${v.inches}"`}</dd>
                  </div>
                ))}
              </dl>

              <button
                type="button"
                onClick={() => router.delete(`/measurements/${p.id}`, { preserveScroll: true })}
                className="mt-4 font-ui text-[12px] uppercase tracking-[0.14em] text-danger hover:underline"
              >
                Remove
              </button>
            </Card>
          ))}
        </div>
      )}

      {!adding && profiles.data.length > 0 ? (
        <Button className="mt-8" variant="ghost" onClick={() => setAdding(true)}>
          Add another profile
        </Button>
      ) : null}

      {adding ? (
        <Card className="mt-8">
          <h2 className="font-display text-2xl">New measurement profile</h2>

          <div className="mt-5 max-w-sm">
            <Field label="Name" required error={errors.name}>
              <Input value={name} onChange={(e) => setName(e.target.value)} placeholder="My kaftan fit" />
            </Field>
          </div>

          {[['Top', top], ['Trouser', trouser]].map(([label, group]) => (
            <section key={label as string} className="mt-8">
              <h3 className="eyebrow text-ink">{label as string}</h3>
              <div className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {(group as MeasurementField[]).map((f) => (
                  <Field
                    key={f.key}
                    label={f.label}
                    hint={
                      unit === 'cm'
                        ? `${inchesToCm(f.min_inches)}–${inchesToCm(f.max_inches)} cm`
                        : `${f.min_inches}–${f.max_inches} in`
                    }
                    error={errors[`values.${f.key}`]}
                  >
                    <Input
                      type="number"
                      step="0.25"
                      inputMode="decimal"
                      value={values[f.key] ?? ''}
                      onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
                    />
                  </Field>
                ))}
              </div>
            </section>
          ))}

          <div className="mt-8 flex gap-3">
            <Button
              onClick={() =>
                router.post(
                  '/measurements',
                  { name, unit, source: 'manual', is_default: profiles.data.length === 0, values },
                  {
                    preserveScroll: true,
                    onError: (e) => setErrors(e as Record<string, string>),
                    onSuccess: () => {
                      setAdding(false)
                      setValues({})
                      setName('')
                    },
                  },
                )
              }
            >
              Save profile
            </Button>
            <Button variant="quiet" onClick={() => setAdding(false)}>
              Cancel
            </Button>
          </div>
        </Card>
      ) : null}
    </StorefrontLayout>
  )
}

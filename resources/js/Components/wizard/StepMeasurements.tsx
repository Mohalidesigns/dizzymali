import { useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { Badge, Button, Card, ErrorNote, Eyebrow, Field, Input } from '../Ui'
import { cmToInches, cx, inchesToCm } from '../../lib/format'
import type { GarmentType, MeasurementProfile } from '../../types'

type Mode = 'saved' | 'enter' | 'upload'

export default function StepMeasurements({
  garmentType,
  profiles,
  selectedProfileId,
  unit,
  onUnitChange,
  onSelectProfile,
}: {
  garmentType: GarmentType | null
  profiles: MeasurementProfile[]
  selectedProfileId: number | null
  unit: 'in' | 'cm'
  onUnitChange: (unit: 'in' | 'cm') => void
  onSelectProfile: (id: number) => void
}) {
  const [mode, setMode] = useState<Mode>(profiles.length > 0 ? 'saved' : 'enter')
  const [values, setValues] = useState<Record<string, string>>({})
  const [name, setName] = useState('My measurements')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const fields = garmentType?.measurement_fields ?? []
  const groups = useMemo(
    () => ({
      top: fields.filter((f) => f.group === 'top'),
      trouser: fields.filter((f) => f.group === 'trouser'),
    }),
    [fields],
  )

  // Bounds are stored in inches; show them in whatever unit is on screen.
  const bounds = (min: number, max: number) =>
    unit === 'cm'
      ? `${inchesToCm(min)}–${inchesToCm(max)} cm`
      : `${min}–${max} in`

  function save() {
    setSaving(true)
    setErrors({})

    router.post(
      '/measurements',
      { name, unit, source: 'manual', is_default: profiles.length === 0, values },
      {
        preserveScroll: true,
        onError: (e) => setErrors(e as Record<string, string>),
        onFinish: () => setSaving(false),
        onSuccess: () => setMode('saved'),
      },
    )
  }

  return (
    <div>
      <Eyebrow>Step 3 of 6</Eyebrow>
      <h2 className="mt-3 font-display text-4xl">Your measurements</h2>
      <p className="mt-2 max-w-xl text-ink-muted">
        The fit is the whole job. Take your time here — we check every figure against a sensible range
        before anything is cut.
      </p>

      <div className="mt-6 flex items-center justify-between gap-4">
        <div role="tablist" aria-label="How to provide measurements" className="flex flex-wrap gap-2">
          {([
            ['saved', `Use a saved profile${profiles.length ? ` (${profiles.length})` : ''}`],
            ['enter', 'Enter measurements'],
            ['upload', "Upload a tailor's sheet"],
          ] as Array<[Mode, string]>).map(([key, label]) => (
            <button
              key={key}
              role="tab"
              type="button"
              aria-selected={mode === key}
              onClick={() => setMode(key)}
              className={cx(
                'rounded-[--radius-pill] border px-4 py-2 font-ui text-[12px] uppercase tracking-[0.14em]',
                mode === key ? 'border-ink bg-ink text-cream' : 'border-line text-ink-muted hover:text-ink',
              )}
            >
              {label}
            </button>
          ))}
        </div>

        <div className="flex items-center gap-1 rounded-[--radius-pill] border border-line p-1">
          {(['in', 'cm'] as const).map((u) => (
            <button
              key={u}
              type="button"
              onClick={() => onUnitChange(u)}
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

      {mode === 'saved' ? (
        <div className="mt-8 grid gap-4 sm:grid-cols-2">
          {profiles.length === 0 ? (
            <p className="text-ink-muted">
              No saved measurements yet. Enter them once and every future order is a single tap.
            </p>
          ) : (
            profiles.map((p) => (
              <Card key={p.id} interactive selected={selectedProfileId === p.id}>
                <button type="button" onClick={() => onSelectProfile(p.id)} className="w-full text-left">
                  <div className="flex items-center justify-between gap-3">
                    <h3 className="font-display text-xl">{p.name}</h3>
                    {p.is_default ? <Badge tone="gold">Default</Badge> : null}
                  </div>
                  {p.review_status === 'pending_review' ? (
                    <Badge tone="warn">Awaiting our review</Badge>
                  ) : null}
                  <dl className="tabular mt-4 grid grid-cols-2 gap-x-6 gap-y-1 text-[13px]">
                    {(p.values ?? []).slice(0, 8).map((v) => (
                      <div key={v.key} className="flex justify-between">
                        <dt className="text-ink-muted">{v.label}</dt>
                        <dd>{unit === 'cm' ? `${v.cm} cm` : `${v.inches}"`}</dd>
                      </div>
                    ))}
                  </dl>
                </button>
              </Card>
            ))
          )}
        </div>
      ) : null}

      {mode === 'enter' ? (
        <div className="mt-8 space-y-8">
          {errors.error ? <ErrorNote>{errors.error}</ErrorNote> : null}

          <Field label="Name this profile" hint="So you can pick it again next time.">
            <Input value={name} onChange={(e) => setName(e.target.value)} maxLength={80} />
          </Field>

          {(['top', 'trouser'] as const).map((group) =>
            groups[group].length === 0 ? null : (
              <section key={group}>
                <h3 className="eyebrow text-ink">{group === 'top' ? 'Top' : 'Trouser'}</h3>
                <div className="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {groups[group].map((f) => (
                    <Field
                      key={f.key}
                      label={f.label}
                      required={f.is_required}
                      hint={f.help_text ? `${f.help_text} (${bounds(f.min_inches, f.max_inches)})` : bounds(f.min_inches, f.max_inches)}
                      error={errors[`values.${f.key}`]}
                    >
                      <Input
                        type="number"
                        step="0.25"
                        inputMode="decimal"
                        value={values[f.key] ?? ''}
                        onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
                        aria-describedby={`${f.key}-hint`}
                      />
                    </Field>
                  ))}
                </div>
              </section>
            ),
          )}

          <Button type="button" onClick={save} disabled={saving}>
            {saving ? 'Saving…' : 'Save these measurements'}
          </Button>
        </div>
      ) : null}

      {mode === 'upload' ? (
        <Card className="mt-8">
          <h3 className="font-display text-2xl">Send us your tailor&rsquo;s sheet</h3>
          <p className="mt-2 max-w-xl text-[15px] text-ink-muted">
            Photograph or scan the measurement sheet your usual tailor already has. Our team transcribes
            it into a saved profile and confirms it with you before anything is cut. This usually takes
            one working day.
          </p>
          <p className="mt-4 text-[13px] text-ink-muted">
            Upload is enabled on the next screen alongside your inspiration images, so you only pick
            files once.
          </p>
        </Card>
      ) : null}

      <p className="mt-8 text-[13px] text-ink-muted">
        Measurements are stored in inches and shown in your chosen unit. {unit === 'cm' ? `A 40" chest is ${inchesToCm(40)} cm.` : `A 100 cm chest is ${cmToInches(100)}".`}
      </p>
    </div>
  )
}

import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import type { MeasurementField, MeasurementProfile } from '../../types'

export default function MeasurementReviews({
  profiles,
  fields,
}: {
  profiles: { data: MeasurementProfile[] }
  fields: { data: MeasurementField[] }
}) {
  return (
    <AdminLayout title="Measurement reviews">
      <Head title="Measurement reviews" />

      <p className="mb-6 max-w-2xl text-[14px] text-ink-muted">
        Sheets customers uploaded rather than typed. Transcribe each into structured fields and it
        becomes a saved profile they can reuse. Anything out of range is rejected at save time, which
        is the point of doing this before the cloth is cut.
      </p>

      {profiles.data.length === 0 ? (
        <p className="rounded-xl border border-dashed border-line bg-white/60 px-6 py-12 text-center text-ink-muted">
          Nothing waiting. The queue is clear.
        </p>
      ) : (
        <div className="space-y-6">
          {profiles.data.map((p) => (
            <ReviewCard key={p.id} profile={p} fields={fields.data} />
          ))}
        </div>
      )}
    </AdminLayout>
  )
}

function ReviewCard({ profile, fields }: { profile: MeasurementProfile; fields: MeasurementField[] }) {
  const [values, setValues] = useState<Record<string, string>>(() =>
    Object.fromEntries((profile.values ?? []).map((v) => [v.key, String(v.inches)])),
  )
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [rejectNote, setRejectNote] = useState('')

  return (
    <section className="rounded-xl border border-line bg-white p-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h2 className="text-[16px] font-semibold">{profile.name}</h2>
          <p className="text-[13px] text-ink-muted">Source: {profile.source}</p>
        </div>
      </div>

      <div className="mt-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {fields.map((f) => (
          <label key={f.key} className="text-[12px] text-ink-muted">
            {f.label}
            <input
              type="number"
              step="0.25"
              value={values[f.key] ?? ''}
              onChange={(e) => setValues((v) => ({ ...v, [f.key]: e.target.value }))}
              className="mt-1 block w-full rounded-lg border border-line px-3 py-2 text-right text-[14px] tabular-nums text-ink"
            />
            <span className="mt-0.5 block text-[11px] text-ink-faint">
              {f.min_inches}–{f.max_inches} in
            </span>
            {errors[`values.${f.key}`] ? (
              <span className="mt-0.5 block text-[12px] text-danger">{errors[`values.${f.key}`]}</span>
            ) : null}
          </label>
        ))}
      </div>

      <div className="mt-5 flex flex-wrap items-end gap-3 border-t border-line pt-4">
        <button
          type="button"
          onClick={() =>
            router.post(
              `/admin/measurement-reviews/${profile.id}/approve`,
              { name: profile.name, unit: 'in', source: profile.source, values },
              { preserveScroll: true, onError: (e) => setErrors(e as Record<string, string>) },
            )
          }
          className="rounded-lg bg-ink px-4 py-2 text-[14px] font-medium text-cream hover:bg-[#2A2420]"
        >
          Approve and verify
        </button>

        <input
          value={rejectNote}
          onChange={(e) => setRejectNote(e.target.value)}
          placeholder="Reason to send back"
          className="flex-1 rounded-lg border border-line px-3 py-2 text-[14px]"
        />
        <button
          type="button"
          disabled={!rejectNote}
          onClick={() =>
            router.post(
              `/admin/measurement-reviews/${profile.id}/reject`,
              { review_notes: rejectNote },
              { preserveScroll: true },
            )
          }
          className="rounded-lg border border-danger px-4 py-2 text-[14px] text-danger disabled:opacity-40"
        >
          Send back
        </button>
      </div>
    </section>
  )
}

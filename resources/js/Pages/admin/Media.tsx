import { Head, router } from '@inertiajs/react'
import { useRef, useState } from 'react'
import MediaImage, { type MediaImage as MediaImageType } from '../../Components/Media'
import AdminLayout from '../../Layouts/AdminLayout'

type Row = {
  type: string
  id: number
  label: string
  collection: string
  image: MediaImageType
  asset_id: number | null
  processing_status: string | null
}

export default function Media({
  garmentTypes,
  fabricVariants,
  summary,
}: {
  garmentTypes: Row[]
  fabricVariants: Row[]
  summary: { awaiting_photography: number; processing: number; failed: number }
}) {
  return (
    <AdminLayout title="Photography">
      <Head title="Photography" />

      <p className="mb-6 max-w-2xl text-[14px] text-ink-muted">
        Every record without a photograph shows a generated placeholder on the storefront, so nothing
        looks broken while the shoot is pending. Uploading a real image replaces it — there is nothing
        to switch over.
      </p>

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <Stat label="Awaiting photography" value={summary.awaiting_photography} alert={summary.awaiting_photography > 0} />
        <Stat label="Processing" value={summary.processing} />
        <Stat label="Failed" value={summary.failed} alert={summary.failed > 0} />
      </div>

      <Section title="Garments" rows={garmentTypes} />
      <Section title="Fabric swatches" rows={fabricVariants} />
    </AdminLayout>
  )
}

function Section({ title, rows }: { title: string; rows: Row[] }) {
  return (
    <section className="mb-10">
      <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">{title}</h2>
      <div className="mt-3 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {rows.map((row) => (
          <Tile key={`${row.type}-${row.id}`} row={row} />
        ))}
      </div>
    </section>
  )
}

function Tile({ row }: { row: Row }) {
  const input = useRef<HTMLInputElement>(null)
  const [altText, setAltText] = useState('')
  const [busy, setBusy] = useState(false)

  function upload(file: File | null) {
    if (!file) return

    // Alt text is required before publish, so it is required at upload —
    // asking later means it never gets written.
    const alt = altText || window.prompt(`Describe this image of ${row.label}`, row.label)

    if (!alt) return

    const data = new FormData()
    data.append('attachable_type', row.type)
    data.append('attachable_id', String(row.id))
    data.append('collection', row.collection)
    data.append('alt_text', alt)
    data.append('is_primary', '1')
    data.append('file', file)

    setBusy(true)
    router.post('/admin/media', data, {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        setBusy(false)
        setAltText('')
      },
    })
  }

  return (
    <div className="rounded-xl border border-line bg-white p-3">
      <MediaImage image={row.image} ratio="aspect-[4/5]" className="rounded-lg" />

      <p className="mt-2 truncate text-[13px] font-medium" title={row.label}>
        {row.label}
      </p>

      {row.processing_status && row.processing_status !== 'ready' ? (
        <p className="mt-0.5 text-[12px] text-accent-ink capitalize">{row.processing_status}</p>
      ) : null}

      <input
        value={altText}
        onChange={(e) => setAltText(e.target.value)}
        placeholder="Alt text"
        className="mt-2 w-full rounded border border-line px-2 py-1 text-[12px]"
      />

      <div className="mt-2 flex items-center justify-between gap-2">
        <button
          type="button"
          disabled={busy}
          onClick={() => input.current?.click()}
          className="text-[12px] text-accent-ink hover:underline disabled:opacity-50"
        >
          {busy ? 'Uploading…' : row.asset_id ? 'Replace' : 'Upload'}
        </button>

        {row.asset_id ? (
          <button
            type="button"
            onClick={() => router.delete(`/admin/media/${row.asset_id}`, { preserveScroll: true })}
            className="text-[12px] text-danger hover:underline"
          >
            Remove
          </button>
        ) : null}
      </div>

      <input
        ref={input}
        type="file"
        accept="image/jpeg,image/png,image/webp,image/avif,image/heic"
        className="sr-only"
        onChange={(e) => upload(e.target.files?.[0] ?? null)}
      />
    </div>
  )
}

function Stat({ label, value, alert }: { label: string; value: number; alert?: boolean }) {
  return (
    <div className={`rounded-xl border bg-white p-4 ${alert ? 'border-terracotta' : 'border-line'}`}>
      <p className="text-[13px] text-ink-muted">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </div>
  )
}

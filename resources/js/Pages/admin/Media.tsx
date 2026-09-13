import { Head, router, usePage } from '@inertiajs/react'
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
  processing_error: string | null
  preview_url: string | null
}

type Summary = {
  awaiting_photography: number
  processing: number
  failed: number
  stalled: number
  storage_linked: boolean
}

export default function Media({
  accepts,
  garmentTypes,
  fabricVariants,
  summary,
}: {
  /** MIME types this server can decode, for the file picker. */
  accepts: string
  garmentTypes: Row[]
  fabricVariants: Row[]
  summary: Summary
}) {
  const { errors } = usePage().props as { errors: Record<string, string> }

  return (
    <AdminLayout title="Photography">
      <Head title="Photography" />

      <p className="mb-6 max-w-2xl text-[14px] text-ink-muted">
        Every record without a photograph shows a generated placeholder on the storefront, so nothing
        looks broken while the shoot is pending. Uploading a real image replaces it — there is nothing
        to switch over.
      </p>

      {errors.file ? (
        <div role="alert" className="mb-4 rounded-xl border border-danger/30 bg-danger/5 px-4 py-3 text-[13px] text-danger">
          {errors.file}
        </div>
      ) : null}

      {/* An upload is not finished when the file lands — the derivatives are
          generated on the media queue, and only a processed asset is served. */}
      {summary.stalled > 0 ? (
        <div role="alert" className="mb-4 rounded-xl border border-terracotta bg-terracotta/5 px-4 py-3 text-[13px]">
          <p className="font-medium">
            {summary.stalled} upload{summary.stalled === 1 ? '' : 's'} waiting on the media queue.
          </p>
          <p className="mt-1 text-ink-muted">
            The files are stored, but nothing is generating their derivatives, so the storefront still
            shows placeholders. Start a worker:
          </p>
          <code className="mt-2 block rounded bg-ink/5 px-2 py-1 font-mono text-[12px]">
            php artisan queue:work --queue=notifications,media,default
          </code>
          <p className="mt-2 text-ink-muted">
            To clear what is already waiting without a worker: <code className="font-mono">php artisan media:process</code>
          </p>
        </div>
      ) : null}

      {!summary.storage_linked ? (
        <div role="alert" className="mb-4 rounded-xl border border-terracotta bg-terracotta/5 px-4 py-3 text-[13px]">
          <p className="font-medium">The public storage symlink is missing.</p>
          <p className="mt-1 text-ink-muted">
            Processed images will not load until it exists:
          </p>
          <code className="mt-2 block rounded bg-ink/5 px-2 py-1 font-mono text-[12px]">php artisan storage:link</code>
        </div>
      ) : null}

      <div className="mb-6 grid gap-4 sm:grid-cols-3">
        <Stat label="Awaiting photography" value={summary.awaiting_photography} alert={summary.awaiting_photography > 0} />
        <Stat label="Processing" value={summary.processing} alert={summary.stalled > 0} />
        <Stat label="Failed" value={summary.failed} alert={summary.failed > 0} />
      </div>

      <Section title="Garments" rows={garmentTypes} accepts={accepts} />
      <Section title="Fabric swatches" rows={fabricVariants} accepts={accepts} />
    </AdminLayout>
  )
}

function Section({ title, rows, accepts }: { title: string; rows: Row[]; accepts: string }) {
  return (
    <section className="mb-10">
      <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">{title}</h2>
      <div className="mt-3 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
        {rows.map((row) => (
          <Tile key={`${row.type}-${row.id}`} row={row} accepts={accepts} />
        ))}
      </div>
    </section>
  )
}

function Tile({ row, accepts }: { row: Row; accepts: string }) {
  const input = useRef<HTMLInputElement>(null)
  const [altText, setAltText] = useState('')
  const [busy, setBusy] = useState(false)

  const isPending = row.processing_status === 'pending' || row.processing_status === 'processing'
  const hasFailed = row.processing_status === 'failed'

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
        if (input.current) input.current.value = ''
      },
    })
  }

  return (
    <div className="rounded-xl border border-line bg-white p-3">
      {/* While an upload is still in the pipeline, show the original that was
          uploaded rather than the placeholder — otherwise a successful upload is
          indistinguishable from one that never happened. */}
      {row.preview_url ? (
        <figure className="relative aspect-[4/5] overflow-hidden rounded-lg bg-sand">
          <img src={row.preview_url} alt={`${row.label} — uploaded, not yet processed`} className="h-full w-full object-cover" />
          <figcaption className="absolute left-2 top-2 rounded-[--radius-pill] bg-ink/75 px-2.5 py-1 font-ui text-[10px] uppercase tracking-[0.14em] text-cream">
            {hasFailed ? 'Failed' : 'Processing'}
          </figcaption>
        </figure>
      ) : (
        <MediaImage image={row.image} ratio="aspect-[4/5]" className="rounded-lg" />
      )}

      <p className="mt-2 truncate text-[13px] font-medium" title={row.label}>
        {row.label}
      </p>

      {isPending ? (
        <p className="mt-0.5 text-[12px] text-accent-ink">Uploaded — waiting on the media queue</p>
      ) : null}

      {hasFailed ? (
        <p className="mt-0.5 text-[12px] text-danger">
          Processing failed{row.processing_error ? `: ${row.processing_error}` : ''}
        </p>
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

        <div className="flex items-center gap-2">
          {row.asset_id && (hasFailed || isPending) ? (
            <button
              type="button"
              onClick={() => router.post(`/admin/media/${row.asset_id}/retry`, {}, { preserveScroll: true })}
              className="text-[12px] text-ink-muted hover:underline"
            >
              Retry
            </button>
          ) : null}

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
      </div>

      <input
        ref={input}
        type="file"
        accept={accepts}
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

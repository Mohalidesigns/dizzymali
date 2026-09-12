import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'

type Block = {
  id: number
  type: string
  placement: string
  title: string | null
  payload: Record<string, unknown> | null
  sort_order: number
  is_active: boolean
  starts_at: string | null
  ends_at: string | null
  is_live: boolean
  media_count: number
}

export default function Cms({
  blocks,
  types,
  placements,
}: {
  blocks: Block[]
  types: string[]
  placements: string[]
}) {
  const [draft, setDraft] = useState({ type: types[0] ?? 'hero', placement: placements[0] ?? 'homepage', title: '' })

  const grouped = placements.map((placement) => ({
    placement,
    items: blocks.filter((b) => b.placement === placement),
  }))

  return (
    <AdminLayout title="Content">
      <Head title="Content" />

      <p className="mb-6 max-w-2xl text-[14px] text-ink-muted">
        Blocks are scheduled, not just switched on and off — set a start and end and a promo runs
        itself. Nothing publishes while it still has an image without alt text.
      </p>

      <section className="mb-8 rounded-xl border border-line bg-white p-5">
        <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
          New block
        </h2>
        <div className="mt-3 flex flex-wrap items-end gap-3">
          <label className="text-[12px] text-ink-muted">
            Type
            <select
              value={draft.type}
              onChange={(e) => setDraft((d) => ({ ...d, type: e.target.value }))}
              className="mt-1 block rounded-lg border border-line px-3 py-2 text-[14px] text-ink"
            >
              {types.map((t) => (
                <option key={t} value={t}>
                  {t.replace(/_/g, ' ')}
                </option>
              ))}
            </select>
          </label>

          <label className="text-[12px] text-ink-muted">
            Placement
            <select
              value={draft.placement}
              onChange={(e) => setDraft((d) => ({ ...d, placement: e.target.value }))}
              className="mt-1 block rounded-lg border border-line px-3 py-2 text-[14px] text-ink"
            >
              {placements.map((p) => (
                <option key={p} value={p}>
                  {p.replace(/_/g, ' ')}
                </option>
              ))}
            </select>
          </label>

          <label className="flex-1 text-[12px] text-ink-muted">
            Title
            <input
              value={draft.title}
              onChange={(e) => setDraft((d) => ({ ...d, title: e.target.value }))}
              className="mt-1 block w-full rounded-lg border border-line px-3 py-2 text-[14px] text-ink"
            />
          </label>

          <button
            type="button"
            onClick={() =>
              router.post('/admin/cms', draft, {
                preserveScroll: true,
                onSuccess: () => setDraft((d) => ({ ...d, title: '' })),
              })
            }
            className="rounded-lg bg-ink px-4 py-2 text-[14px] font-medium text-cream hover:bg-[#2A2420]"
          >
            Create
          </button>
        </div>
      </section>

      {grouped.map(({ placement, items }) => (
        <section key={placement} className="mb-8">
          <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
            {placement.replace(/_/g, ' ')}
          </h2>

          {items.length === 0 ? (
            <p className="mt-2 text-[14px] text-ink-muted">Nothing here yet.</p>
          ) : (
            <ul className="mt-3 space-y-2">
              {items.map((block, index) => (
                <li
                  key={block.id}
                  className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-line bg-white px-4 py-3"
                >
                  <div className="min-w-0">
                    <p className="truncate text-[15px] font-medium">{block.title ?? '(untitled)'}</p>
                    <p className="text-[13px] text-ink-muted">
                      {block.type.replace(/_/g, ' ')} · {block.media_count} image
                      {block.media_count === 1 ? '' : 's'}
                      {block.starts_at ? ` · from ${block.starts_at}` : ''}
                      {block.ends_at ? ` · until ${block.ends_at}` : ''}
                    </p>
                  </div>

                  <div className="flex items-center gap-3">
                    <span
                      className={`rounded-[--radius-pill] px-2.5 py-1 text-[12px] ${
                        block.is_live ? 'bg-success/10 text-success' : 'bg-sand text-ink-muted'
                      }`}
                    >
                      {block.is_live ? 'Live' : block.is_active ? 'Scheduled' : 'Draft'}
                    </span>

                    <button
                      type="button"
                      onClick={() =>
                        router.patch(
                          `/admin/cms/${block.id}`,
                          { is_active: !block.is_active },
                          { preserveScroll: true },
                        )
                      }
                      className="text-[13px] text-accent-ink hover:underline"
                    >
                      {block.is_active ? 'Unpublish' : 'Publish'}
                    </button>

                    <button
                      type="button"
                      disabled={index === 0}
                      onClick={() => {
                        const ids = items.map((b) => b.id)
                        ;[ids[index - 1], ids[index]] = [ids[index], ids[index - 1]]
                        router.post('/admin/cms/reorder', { ids }, { preserveScroll: true })
                      }}
                      className="text-[13px] text-ink-muted hover:text-ink disabled:opacity-30"
                    >
                      Move up
                    </button>

                    <button
                      type="button"
                      onClick={() => router.delete(`/admin/cms/${block.id}`, { preserveScroll: true })}
                      className="text-[13px] text-danger hover:underline"
                    >
                      Delete
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      ))}
    </AdminLayout>
  )
}

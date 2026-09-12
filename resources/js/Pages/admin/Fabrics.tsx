import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import AdminLayout from '../../Layouts/AdminLayout'
import type { FabricVariant } from '../../types'

type Fabric = {
  id: number
  name: string
  slug: string
  is_active: boolean
  material?: { id: number; name: string }
}

export default function Fabrics({
  fabrics,
  variants,
  materials,
}: {
  fabrics: Fabric[]
  variants: { data: FabricVariant[] }
  materials: Array<{ id: number; name: string }>
}) {
  const [editing, setEditing] = useState<number | null>(null)
  const [draft, setDraft] = useState<{ price: string; stock: string }>({ price: '', stock: '' })

  return (
    <AdminLayout title="Fabrics">
      <Head title="Fabrics" />

      <p className="mb-4 max-w-2xl text-[14px] text-ink-muted">
        Price lives on the variant, not the fabric — the navy and the ivory off the same bolt rarely
        cost the same. Prices are typed in whole naira and stored as kobo.
      </p>

      <div className="overflow-x-auto rounded-xl border border-line bg-white">
        <table className="w-full min-w-[900px] text-left text-[14px]">
          <thead className="border-b border-line text-[12px] uppercase tracking-[0.1em] text-ink-muted">
            <tr>
              <th className="px-4 py-3">Fabric</th>
              <th className="px-4 py-3">Colour</th>
              <th className="px-4 py-3">Material</th>
              <th className="px-4 py-3 text-right">Per yard</th>
              <th className="px-4 py-3 text-right">Available</th>
              <th className="px-4 py-3" />
            </tr>
          </thead>
          <tbody>
            {variants.data.map((v) => (
              <tr key={v.id} className="border-b border-line last:border-0">
                <td className="px-4 py-3">{v.fabric?.name}</td>
                <td className="px-4 py-3">
                  <span className="flex items-center gap-2">
                    <span
                      className="inline-block h-4 w-4 rounded-full border border-line"
                      style={{ backgroundColor: v.colour_hex ?? '#EFE7DC' }}
                    />
                    {v.colour_name}
                  </span>
                </td>
                <td className="px-4 py-3 text-ink-muted">{v.fabric?.material?.name}</td>
                <td className="px-4 py-3 text-right tabular-nums">
                  {editing === v.id ? (
                    <input
                      type="number"
                      value={draft.price}
                      onChange={(e) => setDraft((d) => ({ ...d, price: e.target.value }))}
                      className="w-28 rounded border border-line px-2 py-1 text-right"
                    />
                  ) : (
                    v.price_per_yard.formatted
                  )}
                </td>
                <td
                  className={`px-4 py-3 text-right tabular-nums ${v.is_low_stock ? 'text-terracotta' : ''}`}
                >
                  {editing === v.id ? (
                    <input
                      type="number"
                      step="0.25"
                      value={draft.stock}
                      onChange={(e) => setDraft((d) => ({ ...d, stock: e.target.value }))}
                      className="w-24 rounded border border-line px-2 py-1 text-right"
                    />
                  ) : (
                    `${v.available_yards} yd`
                  )}
                </td>
                <td className="px-4 py-3 text-right">
                  {editing === v.id ? (
                    <span className="flex justify-end gap-3">
                      <button
                        type="button"
                        className="text-accent-ink hover:underline"
                        onClick={() =>
                          router.patch(
                            `/admin/fabric-variants/${v.id}`,
                            {
                              price_per_yard_naira: Number(draft.price),
                              stock_yards: Number(draft.stock),
                            },
                            { preserveScroll: true, onSuccess: () => setEditing(null) },
                          )
                        }
                      >
                        Save
                      </button>
                      <button type="button" className="text-ink-muted" onClick={() => setEditing(null)}>
                        Cancel
                      </button>
                    </span>
                  ) : (
                    <button
                      type="button"
                      className="text-accent-ink hover:underline"
                      onClick={() => {
                        setEditing(v.id)
                        setDraft({
                          price: String(Math.round(v.price_per_yard.minor / 100)),
                          stock: String(v.available_yards),
                        })
                      }}
                    >
                      Edit
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  )
}

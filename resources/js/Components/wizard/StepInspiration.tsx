import { useRef, useState } from 'react'
import { router } from '@inertiajs/react'
import { Button, Card, Eyebrow, Field, Input } from '../Ui'
import { cx } from '../../lib/format'
import type { GarmentType, OrderItem } from '../../types'

export default function StepInspiration({
  garmentType,
  item,
  selectedOptionIds,
  styleNotes,
  onToggleOption,
  onStyleNotes,
}: {
  garmentType: GarmentType | null
  item: OrderItem | null
  selectedOptionIds: number[]
  styleNotes: string
  onToggleOption: (groupId: number, optionId: number, allowsMultiple: boolean) => void
  onStyleNotes: (notes: string) => void
}) {
  const fileInput = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const [dragging, setDragging] = useState(false)

  function upload(files: FileList | null) {
    if (!files || files.length === 0 || !item) return

    const data = new FormData()
    Array.from(files).forEach((f) => data.append('images[]', f))

    setUploading(true)
    router.post(`/order-items/${item.id}/inspirations`, data, {
      preserveScroll: true,
      forceFormData: true,
      onFinish: () => setUploading(false),
    })
  }

  return (
    <div>
      <Eyebrow>Step 4 of 6</Eyebrow>
      <h2 className="mt-3 font-display text-4xl">Style and inspiration</h2>
      <p className="mt-2 max-w-xl text-ink-muted">
        Show us what you have in mind. A photograph of something you already own is worth more than any
        description.
      </p>

      <Card className="mt-8">
        <h3 className="eyebrow text-ink">Reference images</h3>
        <div
          onDragOver={(e) => {
            e.preventDefault()
            setDragging(true)
          }}
          onDragLeave={() => setDragging(false)}
          onDrop={(e) => {
            e.preventDefault()
            setDragging(false)
            upload(e.dataTransfer.files)
          }}
          className={cx(
            'mt-4 rounded-[--radius-input] border-2 border-dashed px-6 py-10 text-center transition-colors',
            dragging ? 'border-terracotta bg-terracotta/5' : 'border-line',
          )}
        >
          <p className="text-[15px] text-ink-muted">
            Drag up to five images here, or{' '}
            <button
              type="button"
              onClick={() => fileInput.current?.click()}
              className="text-accent-ink underline"
            >
              choose files
            </button>
            .
          </p>
          <p className="mt-1 text-[13px] text-ink-faint">JPEG, PNG, WebP or HEIC. Up to 25 MB each.</p>
          <input
            ref={fileInput}
            type="file"
            accept="image/jpeg,image/png,image/webp,image/heic"
            multiple
            capture="environment"
            className="sr-only"
            onChange={(e) => upload(e.target.files)}
          />
        </div>

        {uploading ? <p className="mt-3 text-[13px] text-ink-muted">Uploading…</p> : null}

        {item && item.inspirations.length > 0 ? (
          <ul className="mt-5 grid grid-cols-3 gap-3 sm:grid-cols-5">
            {item.inspirations.map((img) => (
              <li key={img.id} className="group relative">
                <img
                  src={img.url}
                  alt={img.original_name ?? 'Reference image you uploaded'}
                  className="aspect-square w-full rounded-[--radius-input] border border-line object-cover"
                />
                <button
                  type="button"
                  onClick={() => router.delete(`/inspirations/${img.id}`, { preserveScroll: true })}
                  className="absolute right-1 top-1 rounded-full bg-ink/80 px-2 py-0.5 text-[12px] text-cream opacity-0 transition-opacity group-hover:opacity-100 focus:opacity-100"
                  aria-label="Remove this image"
                >
                  ✕
                </button>
              </li>
            ))}
          </ul>
        ) : null}
      </Card>

      {(garmentType?.option_groups ?? []).map((group) => (
        <Card key={group.id} className="mt-6">
          <div className="flex items-baseline justify-between gap-4">
            <h3 className="font-display text-2xl">{group.name}</h3>
            {group.allows_multiple ? <span className="text-[13px] text-ink-muted">Choose any</span> : null}
          </div>
          {group.help_text ? <p className="mt-1 text-[14px] text-ink-muted">{group.help_text}</p> : null}

          <div className="mt-4 grid gap-3 sm:grid-cols-2">
            {group.options.map((option) => {
              const active = selectedOptionIds.includes(option.id)

              return (
                <button
                  key={option.id}
                  type="button"
                  onClick={() => onToggleOption(group.id, option.id, group.allows_multiple)}
                  aria-pressed={active}
                  className={cx(
                    'rounded-[--radius-input] border p-4 text-left transition-colors',
                    active ? 'border-terracotta bg-terracotta/5' : 'border-line hover:border-ink-faint',
                  )}
                >
                  <div className="flex items-baseline justify-between gap-3">
                    <span className="font-medium">{option.name}</span>
                    <span className="tabular text-[13px] text-accent-ink">
                      {option.surcharge.minor === 0 ? 'Included' : `+ ${option.surcharge.formatted}`}
                    </span>
                  </div>
                  {option.description ? (
                    <p className="mt-1 text-[13px] text-ink-muted">{option.description}</p>
                  ) : null}
                  {option.additional_lead_days > 0 ? (
                    <p className="mt-1 text-[12px] text-ink-faint">
                      Adds {option.additional_lead_days} days
                    </p>
                  ) : null}
                </button>
              )
            })}
          </div>
        </Card>
      ))}

      <div className="mt-6">
        <Field label="Anything else we should know" hint="Sleeve width, how you like it to sit, a detail from the photographs.">
          <textarea
            value={styleNotes}
            onChange={(e) => onStyleNotes(e.target.value)}
            rows={4}
            maxLength={2000}
            className="w-full rounded-[--radius-input] border border-line bg-white px-4 py-3 text-[15px] focus:border-accent-ink focus:outline-none"
          />
        </Field>
      </div>
    </div>
  )
}

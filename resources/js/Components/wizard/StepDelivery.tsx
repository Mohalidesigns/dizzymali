import { useState } from 'react'
import { router } from '@inertiajs/react'
import { Button, Card, Eyebrow, Field, Input } from '../Ui'
import { cx, shortDate } from '../../lib/format'
import type { Address, GarmentType, Order } from '../../types'

export default function StepDelivery({
  order,
  garmentType,
  addresses,
  onChange,
}: {
  order: Order
  garmentType: GarmentType | null
  addresses: Address[]
  onChange: (patch: Record<string, string | number | boolean | number[] | null | undefined>) => void
}) {
  const [showForm, setShowForm] = useState(addresses.length === 0)
  const item = order.items[0] ?? null

  return (
    <div>
      <Eyebrow>Step 5 of 6</Eyebrow>
      <h2 className="mt-3 font-display text-4xl">Cloth, quantity and delivery</h2>

      <Card className="mt-8">
        <h3 className="eyebrow text-ink">How much cloth</h3>
        {item ? (
          <>
            <p className="mt-3 text-[15px]">
              A {garmentType?.name ?? 'garment'} in your size typically takes{' '}
              <strong className="tabular">{item.yards.base + item.yards.size_adjustment} yards</strong>.
            </p>
            <dl className="tabular mt-4 space-y-1 text-[14px]">
              <div className="flex justify-between border-b border-line pb-1">
                <dt className="text-ink-muted">Base for a {garmentType?.name}</dt>
                <dd>{item.yards.base} yd</dd>
              </div>
              {item.yards.size_adjustment > 0 ? (
                <div className="flex justify-between border-b border-line pb-1">
                  <dt className="text-ink-muted">Adjustment for your measurements</dt>
                  <dd>+ {item.yards.size_adjustment} yd</dd>
                </div>
              ) : null}
              <div className="flex justify-between pt-1 font-medium">
                <dt>Total</dt>
                <dd>{item.yards.total} yd</dd>
              </div>
            </dl>
          </>
        ) : null}

        <div className="mt-6 grid gap-5 sm:grid-cols-2">
          <Field label="Quantity">
            <Input
              type="number"
              min={1}
              max={20}
              value={item?.quantity ?? 1}
              onChange={(e) => onChange({ quantity: Number(e.target.value) })}
            />
          </Field>
          <Field label="Extra yards" hint="Buy extra cloth from the same bolt — for a cap, or a matching piece.">
            <Input
              type="number"
              min={0}
              step="0.5"
              value={item?.yards.customer_extra ?? 0}
              onChange={(e) => onChange({ extra_yards: Number(e.target.value) })}
            />
          </Field>
        </div>
      </Card>

      <Card className="mt-6">
        <h3 className="eyebrow text-ink">Delivery address</h3>

        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          {addresses.map((a) => (
            <button
              key={a.id}
              type="button"
              onClick={() => onChange({ shipping_address_id: a.id })}
              aria-pressed={order.items.length > 0 && a.id === (order as unknown as { shipping_address_id?: number }).shipping_address_id}
              className={cx(
                'rounded-[--radius-input] border p-4 text-left',
                'border-line hover:border-ink-faint',
              )}
            >
              <p className="font-medium">{a.label ?? a.recipient_name}</p>
              <p className="mt-1 text-[13px] text-ink-muted">
                {[a.line_1, a.line_2, a.city, a.state_region, a.postcode, a.country_code]
                  .filter(Boolean)
                  .join(', ')}
              </p>
            </button>
          ))}
        </div>

        {showForm ? (
          <NewAddressForm onDone={() => setShowForm(false)} />
        ) : (
          <button
            type="button"
            onClick={() => setShowForm(true)}
            className="mt-4 font-ui text-[12px] uppercase tracking-[0.14em] text-accent-ink hover:underline"
          >
            Add a new address
          </button>
        )}
      </Card>

      <Card className="mt-6">
        <h3 className="eyebrow text-ink">Service level</h3>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          {([
            ['standard', 'Standard', 'The normal workshop queue.'],
            ['express', 'Express', 'Moved to the front and shipped by courier. A surcharge applies to the sewing.'],
          ] as const).map(([value, label, body]) => (
            <button
              key={value}
              type="button"
              onClick={() => onChange({ service_level: value })}
              aria-pressed={order.service_level === value}
              className={cx(
                'rounded-[--radius-input] border p-4 text-left',
                order.service_level === value ? 'border-terracotta bg-terracotta/5' : 'border-line hover:border-ink-faint',
              )}
            >
              <p className="font-medium">{label}</p>
              <p className="mt-1 text-[13px] text-ink-muted">{body}</p>
            </button>
          ))}
        </div>

        {order.promised_at ? (
          <p className="mt-4 text-[14px] text-ink-muted">
            Promised for <strong className="text-ink">{shortDate(order.promised_at)}</strong>.
          </p>
        ) : null}
      </Card>
    </div>
  )
}

function NewAddressForm({ onDone }: { onDone: () => void }) {
  const [form, setForm] = useState({
    recipient_name: '',
    phone: '',
    line_1: '',
    line_2: '',
    city: '',
    state_region: '',
    postcode: '',
    country_code: 'NG',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const set = (key: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm((f) => ({ ...f, [key]: e.target.value }))

  return (
    <div className="mt-5 grid gap-4 border-t border-line pt-5 sm:grid-cols-2">
      <Field label="Recipient name" required error={errors.recipient_name}>
        <Input value={form.recipient_name} onChange={set('recipient_name')} />
      </Field>
      <Field label="Phone" required error={errors.phone}>
        <Input value={form.phone} onChange={set('phone')} />
      </Field>
      <Field label="Address line 1" required error={errors.line_1}>
        <Input value={form.line_1} onChange={set('line_1')} />
      </Field>
      <Field label="Address line 2" error={errors.line_2}>
        <Input value={form.line_2} onChange={set('line_2')} />
      </Field>
      <Field label="City" required error={errors.city}>
        <Input value={form.city} onChange={set('city')} />
      </Field>
      <Field label="State or region" error={errors.state_region}>
        <Input value={form.state_region} onChange={set('state_region')} />
      </Field>
      <Field label="Postcode" error={errors.postcode}>
        <Input value={form.postcode} onChange={set('postcode')} />
      </Field>
      <Field label="Country code" hint="Two letters, e.g. NG, GB, US." required error={errors.country_code}>
        <Input value={form.country_code} maxLength={2} onChange={set('country_code')} />
      </Field>

      <div className="sm:col-span-2">
        <Button
          type="button"
          disabled={saving}
          onClick={() => {
            setSaving(true)
            router.post('/addresses', form, {
              preserveScroll: true,
              onError: (e) => setErrors(e as Record<string, string>),
              onSuccess: onDone,
              onFinish: () => setSaving(false),
            })
          }}
        >
          {saving ? 'Saving…' : 'Save address'}
        </Button>
      </div>
    </div>
  )
}

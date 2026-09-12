import { Head, router } from '@inertiajs/react'
import { useState } from 'react'
import { Badge, Button, Card, ErrorNote, Eyebrow } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import { cx } from '../../lib/format'
import type { Money, Order } from '../../types'

type Props = {
  order: { data: Order } | Order
  gateway: { name: string; label: string } | null
  deposit: { percent: number; amount: Money; balance: Money } | null
  bankTransfer: { account_name: string | null; account_number: string | null; bank_name: string | null } | null
  balanceDue: Money
  errors?: Record<string, string>
}

export default function Checkout(props: Props) {
  const order = 'data' in props.order ? props.order.data : props.order
  const [kind, setKind] = useState<'full' | 'deposit'>('full')
  const [method, setMethod] = useState<'gateway' | 'manual'>(props.gateway ? 'gateway' : 'manual')
  const [busy, setBusy] = useState(false)

  const payable = kind === 'deposit' && props.deposit ? props.deposit.amount : props.balanceDue

  return (
    <StorefrontLayout>
      <Head title={`Pay for ${order.reference}`} />

      <div className="mx-auto max-w-3xl">
        <Eyebrow>{order.reference}</Eyebrow>
        <h1 className="mt-2 font-display text-[clamp(2rem,5vw,3.2rem)]">Complete your order</h1>

        {props.errors?.payment ? (
          <div className="mt-6">
            <ErrorNote>{props.errors.payment}</ErrorNote>
          </div>
        ) : null}

        {/* If nothing can take this currency, say so now rather than after a
            card number has been typed. */}
        {!props.gateway && !props.bankTransfer ? (
          <div className="mt-6">
            <ErrorNote>
              We cannot take card payments in {order.currency_code} at the moment. Please get in touch
              and we will arrange it directly.
            </ErrorNote>
          </div>
        ) : null}

        <Card className="mt-8">
          <h2 className="eyebrow text-ink">What you are paying for</h2>
          <dl className="tabular mt-4 space-y-2 text-[15px]">
            <Row label="Subtotal" value={order.totals.subtotal.formatted} />
            <Row label="Delivery" value={order.totals.shipping.formatted} />
            {order.totals.discount.minor > 0 ? (
              <Row label="Discount" value={`− ${order.totals.discount.formatted}`} />
            ) : null}
            <div className="flex items-baseline justify-between border-t border-line pt-3 text-[20px] font-medium">
              <dt>Order total</dt>
              <dd>{order.totals.total.formatted}</dd>
            </div>
            {order.totals.paid.minor > 0 ? (
              <Row label="Already paid" value={`− ${order.totals.paid.formatted}`} />
            ) : null}
          </dl>

          {order.currency_code !== 'NGN' && order.totals.display_total ? (
            <p className="mt-4 rounded-[--radius-input] bg-sand/60 px-4 py-3 text-[13px] text-ink-muted">
              You will be charged <strong className="text-ink">{order.totals.display_total.formatted}</strong>.
              This rate was fixed when you placed the order — it will not change if the naira moves.
            </p>
          ) : null}
        </Card>

        {props.deposit ? (
          <Card className="mt-6">
            <h2 className="eyebrow text-ink">How much to pay now</h2>
            <div className="mt-4 grid gap-3 sm:grid-cols-2">
              <Choice
                active={kind === 'full'}
                onClick={() => setKind('full')}
                title="Pay in full"
                body="Settle the whole amount now."
                amount={props.balanceDue.formatted}
              />
              <Choice
                active={kind === 'deposit'}
                onClick={() => setKind('deposit')}
                title={`${props.deposit.percent}% deposit`}
                body={`${props.deposit.balance.formatted} due before your garment ships.`}
                amount={props.deposit.amount.formatted}
              />
            </div>
          </Card>
        ) : null}

        <Card className="mt-6">
          <h2 className="eyebrow text-ink">How you would like to pay</h2>

          <div className="mt-4 space-y-3">
            {props.gateway ? (
              <Choice
                active={method === 'gateway'}
                onClick={() => setMethod('gateway')}
                title={`Card — via ${props.gateway.label}`}
                body="You will be taken to a secure checkout. Your card details never reach us."
              />
            ) : null}

            {props.bankTransfer ? (
              <Choice
                active={method === 'manual'}
                onClick={() => setMethod('manual')}
                title="Bank transfer"
                body="Transfer to our account and we will confirm as soon as it lands."
              />
            ) : null}
          </div>

          {method === 'manual' && props.bankTransfer ? (
            <dl className="tabular mt-5 space-y-2 rounded-[--radius-input] bg-sand/60 px-4 py-4 text-[14px]">
              <Row label="Bank" value={props.bankTransfer.bank_name ?? '—'} />
              <Row label="Account name" value={props.bankTransfer.account_name ?? '—'} />
              <Row label="Account number" value={props.bankTransfer.account_number ?? '—'} />
              <Row label="Reference" value={order.reference} />
              <p className="pt-2 text-[13px] text-ink-muted">
                Please quote the reference above so we can match your transfer to this order.
              </p>
            </dl>
          ) : null}

          <div className="mt-6 flex items-center justify-between gap-4">
            <div>
              <p className="eyebrow">Paying now</p>
              <p className="tabular font-display text-3xl">{payable.formatted}</p>
            </div>
            <Button
              variant="accent"
              disabled={busy || (!props.gateway && method === 'gateway')}
              onClick={() => {
                setBusy(true)
                router.post(
                  `/checkout/${order.id}/pay`,
                  { kind, gateway: method === 'manual' ? 'manual' : undefined },
                  { onFinish: () => setBusy(false) },
                )
              }}
            >
              {busy ? 'One moment…' : method === 'manual' ? 'Confirm transfer' : 'Pay now'}
            </Button>
          </div>
        </Card>

        <p className="mt-6 text-center text-[13px] text-ink-muted">
          We recalculate every total on our own server before charging. The figure above is the figure
          you pay.
        </p>
      </div>
    </StorefrontLayout>
  )
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-baseline justify-between gap-6">
      <dt className="text-ink-muted">{label}</dt>
      <dd>{value}</dd>
    </div>
  )
}

function Choice({
  active,
  onClick,
  title,
  body,
  amount,
}: {
  active: boolean
  onClick: () => void
  title: string
  body: string
  amount?: string
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={cx(
        'w-full rounded-[--radius-input] border p-4 text-left transition-colors',
        active ? 'border-terracotta bg-terracotta/5' : 'border-line hover:border-ink-faint',
      )}
    >
      <div className="flex items-baseline justify-between gap-3">
        <span className="font-medium">{title}</span>
        {amount ? <span className="tabular text-[15px]">{amount}</span> : null}
      </div>
      <p className="mt-1 text-[13px] text-ink-muted">{body}</p>
    </button>
  )
}

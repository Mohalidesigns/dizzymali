import { Head, Link } from '@inertiajs/react'
import AdminLayout from '../../Layouts/AdminLayout'
import type { Money } from '../../types'

type Props = {
  revenue: { today: Money; week: Money; month: Money }
  ordersByStage: Record<string, { label: string; count: number }>
  lowStock: Array<{ id: number; name: string; available_yards: number; threshold: number }>
  overdue: Array<{ id: number; reference: string; customer: string | null; promised_at: string | null; status: string }>
  pendingMeasurementReviews: number
  newCustomers: number
}

export default function Dashboard(props: Props) {
  return (
    <AdminLayout title="Dashboard">
      <Head title="Dashboard" />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <Stat label="Revenue today" value={props.revenue.today.formatted} />
        <Stat label="Last 7 days" value={props.revenue.week.formatted} />
        <Stat label="Last 30 days" value={props.revenue.month.formatted} />
        <Stat label="New customers (30d)" value={String(props.newCustomers)} />
        <Stat
          label="Measurement sheets to review"
          value={String(props.pendingMeasurementReviews)}
          href="/admin/measurement-reviews"
          alert={props.pendingMeasurementReviews > 0}
        />
      </div>

      <section className="mt-8">
        <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">
          Orders by stage
        </h2>
        <div className="mt-3 grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
          {Object.entries(props.ordersByStage).map(([key, stage]) => (
            <Link
              key={key}
              href={`/admin/orders?status=${key}`}
              className="rounded-xl border border-line bg-white p-4 transition-colors hover:border-ink-faint"
            >
              <p className="text-[13px] text-ink-muted">{stage.label}</p>
              <p className="mt-1 text-2xl font-semibold">{stage.count}</p>
            </Link>
          ))}
        </div>
      </section>

      <div className="mt-8 grid gap-6 lg:grid-cols-2">
        <Panel title="Past the promised date" empty="Nothing is late. Good.">
          {props.overdue.map((o) => (
            <Link
              key={o.id}
              href={`/admin/orders/${o.id}`}
              className="flex items-center justify-between gap-4 border-b border-line py-2.5 text-[14px] last:border-0 hover:text-accent-ink"
            >
              <span>
                <span className="font-medium">{o.reference}</span>
                <span className="ml-2 text-ink-muted">{o.customer}</span>
              </span>
              <span className="text-danger">{o.promised_at}</span>
            </Link>
          ))}
        </Panel>

        <Panel title="Fabric running low" empty="Stock levels are healthy.">
          {props.lowStock.map((f) => (
            <div
              key={f.id}
              className="flex items-center justify-between gap-4 border-b border-line py-2.5 text-[14px] last:border-0"
            >
              <span>{f.name}</span>
              <span className="text-terracotta">
                {f.available_yards} yd left (alert at {f.threshold})
              </span>
            </div>
          ))}
        </Panel>
      </div>
    </AdminLayout>
  )
}

function Stat({
  label,
  value,
  href,
  alert,
}: {
  label: string
  value: string
  href?: string
  alert?: boolean
}) {
  const body = (
    <div
      className={`rounded-xl border bg-white p-4 ${alert ? 'border-terracotta' : 'border-line'}`}
    >
      <p className="text-[13px] text-ink-muted">{label}</p>
      <p className="mt-1 text-2xl font-semibold tabular-nums">{value}</p>
    </div>
  )

  return href ? <Link href={href}>{body}</Link> : body
}

function Panel({
  title,
  empty,
  children,
}: {
  title: string
  empty: string
  children: React.ReactNode
}) {
  const isEmpty = Array.isArray(children) ? children.length === 0 : !children

  return (
    <section className="rounded-xl border border-line bg-white p-5">
      <h2 className="text-[12px] font-semibold uppercase tracking-[0.14em] text-ink-muted">{title}</h2>
      <div className="mt-3">{isEmpty ? <p className="text-[14px] text-ink-muted">{empty}</p> : children}</div>
    </section>
  )
}

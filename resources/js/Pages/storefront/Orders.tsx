import { Head, Link } from '@inertiajs/react'
import { Badge, ButtonLink, Card, EmptyState, PageTitle } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import { shortDate } from '../../lib/format'
import type { Order } from '../../types'

export default function Orders({ orders }: { orders: { data: Order[] } }) {
  return (
    <StorefrontLayout>
      <Head title="My orders" />
      <PageTitle sub="Everything you have commissioned, and where each piece has got to.">
        My orders
      </PageTitle>

      {orders.data.length === 0 ? (
        <EmptyState
          title="Nothing here yet"
          body="When you commission a garment it will appear here, with a photograph at every stage."
          action={<ButtonLink href="/order" variant="accent">Start your first garment</ButtonLink>}
        />
      ) : (
        <div className="grid gap-5 md:grid-cols-2">
          {orders.data.map((order) => (
            <Link key={order.id} href={`/orders/${order.id}`}>
              <Card interactive className="flex h-full flex-col">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="eyebrow">{order.reference}</p>
                    <h2 className="mt-1 font-display text-2xl">
                      {order.items[0]?.garment_type?.name ?? 'Draft order'}
                    </h2>
                    <p className="mt-1 text-[14px] text-ink-muted">
                      {order.items[0]?.fabric_variant?.name ?? 'No fabric chosen yet'}
                    </p>
                  </div>
                  <Badge tone={order.is_overdue ? 'danger' : 'neutral'}>
                    {order.timeline.current_label}
                  </Badge>
                </div>

                <div className="mt-auto flex items-end justify-between gap-4 border-t border-line pt-4">
                  <p className="text-[13px] text-ink-muted">
                    {order.promised_at ? `Promised ${shortDate(order.promised_at)}` : 'Not yet submitted'}
                  </p>
                  <p className="tabular font-display text-xl">{order.totals.total.formatted}</p>
                </div>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </StorefrontLayout>
  )
}

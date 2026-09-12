import { Link, router, usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'
import { cx } from '../lib/format'
import type { PageProps } from '../types'

const sections = [
  { href: '/admin', label: 'Dashboard' },
  { href: '/admin/orders', label: 'Orders' },
  { href: '/admin/payments', label: 'Payments' },
  { href: '/admin/fabrics', label: 'Fabrics' },
  { href: '/admin/garment-types', label: 'Garments & yardage' },
  { href: '/admin/measurement-reviews', label: 'Measurement reviews' },
  { href: '/admin/customers', label: 'Customers' },
  { href: '/admin/media', label: 'Photography' },
  { href: '/admin/cms', label: 'Content' },
  { href: '/admin/shipping', label: 'Shipping' },
  { href: '/admin/currencies', label: 'Currencies' },
]

export default function AdminLayout({ title, children }: { title: string; children: ReactNode }) {
  const { auth, flash } = usePage<PageProps>().props
  const path = typeof window === 'undefined' ? '' : window.location.pathname

  return (
    <div className="admin-root flex min-h-screen bg-[#F7F6F4] text-ink">
      <aside className="hidden w-60 shrink-0 border-r border-line bg-white lg:block">
        <div className="px-5 py-5">
          <Link href="/" className="font-display text-xl">
            DizzyMali
          </Link>
          <p className="mt-0.5 text-[12px] uppercase tracking-[0.18em] text-ink-muted">Workshop</p>
        </div>
        <nav aria-label="Admin sections" className="px-2 pb-6">
          {sections.map((s) => {
            const active = s.href === '/admin' ? path === '/admin' : path.startsWith(s.href)
            return (
              <Link
                key={s.href}
                href={s.href}
                className={cx(
                  'block rounded-lg px-3 py-2 text-[14px] transition-colors',
                  active ? 'bg-sand font-medium text-ink' : 'text-ink-muted hover:bg-sand/60 hover:text-ink',
                )}
              >
                {s.label}
              </Link>
            )
          })}
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex items-center justify-between gap-4 border-b border-line bg-white px-6 py-4">
          <h1 className="text-[18px] font-semibold">{title}</h1>
          <div className="flex items-center gap-4 text-[13px] text-ink-muted">
            <span>{auth.user?.name}</span>
            <button
              type="button"
              onClick={() => router.post('/logout')}
              className="text-accent-ink hover:underline"
            >
              Sign out
            </button>
          </div>
        </header>

        {flash.success ? (
          <div role="status" className="border-b border-success/20 bg-success/10 px-6 py-2.5 text-[13px] text-success">
            {flash.success}
          </div>
        ) : null}

        <main className="min-w-0 flex-1 p-6">{children}</main>
      </div>
    </div>
  )
}

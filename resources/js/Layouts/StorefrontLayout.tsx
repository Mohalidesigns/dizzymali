import { Link, usePage } from '@inertiajs/react'
import type { ReactNode } from 'react'
import { cx } from '../lib/format'
import type { PageProps } from '../types'

const nav = [
  { href: '/garments', label: 'Garments' },
  { href: '/fabrics', label: 'Fabrics' },
  { href: '/order', label: 'Commission' },
]

export default function StorefrontLayout({ children }: { children: ReactNode }) {
  const { auth, flash } = usePage<PageProps>().props
  const path = typeof window === 'undefined' ? '' : window.location.pathname

  return (
    <div className="flex min-h-screen flex-col bg-cream">
      <header className="sticky top-0 z-40 border-b border-line bg-cream/90 backdrop-blur">
        <div className="mx-auto flex max-w-[1280px] items-center justify-between gap-6 px-6 py-4">
          <Link href="/" className="font-display text-2xl tracking-tight">
            DizzyMali
          </Link>

          <nav aria-label="Main" className="hidden items-center gap-8 md:flex">
            {nav.map((item) => (
              <Link
                key={item.href}
                href={item.href}
                className={cx(
                  'font-ui text-[13px] uppercase tracking-[0.18em] transition-colors',
                  path.startsWith(item.href) ? 'text-ink' : 'text-ink-muted hover:text-ink',
                )}
              >
                {item.label}
              </Link>
            ))}
          </nav>

          <div className="flex items-center gap-4">
            {auth.user ? (
              <>
                <Link href="/orders" className="font-ui text-[13px] uppercase tracking-[0.18em] text-ink-muted hover:text-ink">
                  My orders
                </Link>
                {auth.user.is_back_office ? (
                  <Link href="/admin" className="font-ui text-[13px] uppercase tracking-[0.18em] text-accent-ink">
                    Admin
                  </Link>
                ) : null}
              </>
            ) : (
              <Link href="/login" className="font-ui text-[13px] uppercase tracking-[0.18em] text-ink-muted hover:text-ink">
                Sign in
              </Link>
            )}
          </div>
        </div>
      </header>

      {flash.success ? (
        <div role="status" className="border-b border-success/20 bg-success/10">
          <p className="mx-auto max-w-[1280px] px-6 py-3 text-[14px] text-success">{flash.success}</p>
        </div>
      ) : null}

      <main className="mx-auto w-full max-w-[1280px] flex-1 px-6 py-12">{children}</main>

      <footer className="border-t border-line bg-sand/40">
        <div className="mx-auto flex max-w-[1280px] flex-col gap-6 px-6 py-12 md:flex-row md:items-start md:justify-between">
          <div className="max-w-sm">
            <p className="font-display text-xl">DizzyMali</p>
            <p className="mt-2 text-[14px] text-ink-muted">
              Made-to-measure Agbada, Kaftan, Jalabiya and Danshiki. Cut in Nigeria, sent anywhere.
            </p>
          </div>
          <nav aria-label="Footer" className="flex flex-wrap gap-x-10 gap-y-3">
            {nav.map((item) => (
              <Link key={item.href} href={item.href} className="text-[14px] text-ink-muted hover:text-ink">
                {item.label}
              </Link>
            ))}
            <Link href="/measurements" className="text-[14px] text-ink-muted hover:text-ink">
              My measurements
            </Link>
          </nav>
        </div>
      </footer>
    </div>
  )
}

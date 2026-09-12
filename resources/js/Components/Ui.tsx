import { Link } from '@inertiajs/react'
import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode } from 'react'
import { cx } from '../lib/format'

/* ---------------------------------------------------------------- Button */

type ButtonVariant = 'primary' | 'accent' | 'ghost' | 'quiet' | 'danger'

const buttonBase =
  'inline-flex items-center justify-center gap-2 font-ui text-[13px] font-medium uppercase tracking-[0.16em] ' +
  'rounded-[--radius-pill] px-6 py-3 transition-colors disabled:opacity-45 disabled:cursor-not-allowed'

const buttonVariants: Record<ButtonVariant, string> = {
  primary: 'bg-ink text-cream hover:bg-[#2A2420]',
  // Ink on terracotta, never white: 5.3:1 rather than 3.5:1.
  accent: 'bg-terracotta text-ink hover:bg-[#CC5809]',
  ghost: 'border border-line bg-transparent text-ink hover:bg-sand',
  quiet: 'bg-transparent text-ink-muted hover:text-ink',
  danger: 'bg-danger text-white hover:bg-[#A5322A]',
}

export function Button({
  variant = 'primary',
  className,
  ...props
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant }) {
  return <button className={cx(buttonBase, buttonVariants[variant], className)} {...props} />
}

export function ButtonLink({
  variant = 'primary',
  className,
  href,
  children,
}: {
  variant?: ButtonVariant
  className?: string
  href: string
  children: ReactNode
}) {
  return (
    <Link href={href} className={cx(buttonBase, buttonVariants[variant], className)}>
      {children}
    </Link>
  )
}

/* ------------------------------------------------------------------ Card */

export function Card({
  children,
  className,
  interactive = false,
  selected = false,
}: {
  children: ReactNode
  className?: string
  interactive?: boolean
  selected?: boolean
}) {
  return (
    <div
      className={cx(
        'rounded-[--radius-card] border bg-white p-6',
        selected ? 'border-terracotta ring-1 ring-terracotta' : 'border-line',
        interactive && 'cursor-pointer transition-shadow hover:shadow-[0_8px_30px_rgba(20,17,15,0.08)]',
        className,
      )}
    >
      {children}
    </div>
  )
}

/* ------------------------------------------------------------ Typography */

export function Eyebrow({ children, className }: { children: ReactNode; className?: string }) {
  return <p className={cx('eyebrow', className)}>{children}</p>
}

export function PageTitle({ children, sub }: { children: ReactNode; sub?: ReactNode }) {
  return (
    <header className="mb-10">
      <h1 className="font-display text-[clamp(2rem,5vw,3.4rem)]">{children}</h1>
      {sub ? <p className="mt-3 max-w-2xl text-ink-muted">{sub}</p> : null}
    </header>
  )
}

/* ---------------------------------------------------------------- Fields */

export function Field({
  label,
  hint,
  error,
  required,
  children,
}: {
  label: string
  hint?: string | null
  error?: string
  required?: boolean
  children: ReactNode
}) {
  return (
    <label className="block">
      <span className="eyebrow mb-1.5 block text-ink">
        {label}
        {required ? <span className="text-accent-ink"> *</span> : null}
      </span>
      {children}
      {hint && !error ? <span className="mt-1.5 block text-[13px] text-ink-muted">{hint}</span> : null}
      {error ? (
        <span role="alert" className="mt-1.5 block text-[13px] text-danger">
          {error}
        </span>
      ) : null}
    </label>
  )
}

export function Input({ className, ...props }: InputHTMLAttributes<HTMLInputElement>) {
  return (
    <input
      className={cx(
        'w-full rounded-[--radius-input] border border-line bg-white px-4 py-2.5 text-[15px]',
        'placeholder:text-ink-faint focus:border-accent-ink focus:outline-none',
        className,
      )}
      {...props}
    />
  )
}

/* ----------------------------------------------------------------- State */

export function EmptyState({ title, body, action }: { title: string; body: string; action?: ReactNode }) {
  return (
    <div className="rounded-[--radius-card] border border-dashed border-line bg-white/60 px-6 py-16 text-center">
      <h3 className="font-display text-2xl">{title}</h3>
      <p className="mx-auto mt-2 max-w-md text-ink-muted">{body}</p>
      {action ? <div className="mt-6">{action}</div> : null}
    </div>
  )
}

export function Spinner({ label = 'Loading' }: { label?: string }) {
  return (
    <div role="status" aria-live="polite" className="flex items-center gap-3 text-ink-muted">
      <span className="h-4 w-4 animate-spin rounded-full border-2 border-line border-t-terracotta" />
      <span className="text-[13px]">{label}</span>
    </div>
  )
}

export function ErrorNote({ children }: { children: ReactNode }) {
  return (
    <div role="alert" className="rounded-[--radius-input] border border-danger/30 bg-danger/5 px-4 py-3 text-[14px] text-danger">
      {children}
    </div>
  )
}

export function Badge({
  children,
  tone = 'neutral',
}: {
  children: ReactNode
  tone?: 'neutral' | 'success' | 'warn' | 'danger' | 'gold'
}) {
  const tones = {
    neutral: 'bg-sand text-ink',
    success: 'bg-success/10 text-success',
    warn: 'bg-terracotta/15 text-accent-ink',
    danger: 'bg-danger/10 text-danger',
    gold: 'bg-gold/15 text-[#7A6110]',
  }

  return (
    <span className={cx('inline-block rounded-[--radius-pill] px-3 py-1 font-ui text-[12px] tracking-[0.1em] uppercase', tones[tone])}>
      {children}
    </span>
  )
}

/* -------------------------------------------------------------- Swatch */

export function Swatch({ hex, size = 40 }: { hex: string | null; size?: number }) {
  return (
    <span
      aria-hidden="true"
      className="inline-block rounded-full border border-line"
      style={{ width: size, height: size, backgroundColor: hex ?? '#EFE7DC' }}
    />
  )
}

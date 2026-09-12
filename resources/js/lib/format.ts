/**
 * Display helpers only.
 *
 * The frontend never does arithmetic on money — every figure shown comes
 * pre-calculated and pre-formatted from the server. These functions exist to
 * render what arrived, not to compute anything new.
 */

export function yards(value: number): string {
  return `${value.toFixed(2).replace(/\.?0+$/, '')} yd`
}

export function inchesToCm(inches: number): number {
  return Math.round(inches * 2.54 * 10) / 10
}

export function cmToInches(cm: number): number {
  return Math.round((cm / 2.54) * 100) / 100
}

export function displayMeasurement(inches: number, unit: 'in' | 'cm'): string {
  return unit === 'cm' ? `${inchesToCm(inches)} cm` : `${inches}"`
}

export function shortDate(iso: string | null): string {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  })
}

export function relativeDays(iso: string | null): string {
  if (!iso) return ''
  const days = Math.round((new Date(iso).getTime() - Date.now()) / 86_400_000)
  if (days === 0) return 'today'
  if (days === 1) return 'tomorrow'
  if (days === -1) return 'yesterday'
  return days > 0 ? `in ${days} days` : `${Math.abs(days)} days ago`
}

export function cx(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ')
}

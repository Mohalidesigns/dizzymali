import { cx } from '../lib/format'

export type MediaImage = {
  src: string
  srcset: string
  alt: string
  blurhash: string | null
  is_placeholder: boolean
}

/**
 * One component for every catalogue image.
 *
 * A record with no photograph yet renders a generated placeholder rather than a
 * broken frame, and says so quietly in the corner so staff can see what is still
 * missing. When a real image is uploaded nothing here changes — the payload
 * simply stops being a placeholder.
 */
export default function Media({
  image,
  className,
  sizes = '(max-width: 640px) 100vw, 33vw',
  ratio = 'aspect-[4/5]',
  showPlaceholderBadge = true,
  loading = 'lazy',
}: {
  image: MediaImage
  className?: string
  sizes?: string
  ratio?: string
  showPlaceholderBadge?: boolean
  loading?: 'lazy' | 'eager'
}) {
  return (
    <figure className={cx('relative overflow-hidden bg-sand', ratio, className)}>
      <img
        src={image.src}
        srcSet={image.srcset || undefined}
        sizes={image.srcset ? sizes : undefined}
        alt={image.alt}
        loading={loading}
        decoding="async"
        className="h-full w-full object-cover"
      />
      {image.is_placeholder && showPlaceholderBadge ? (
        <figcaption className="absolute left-2 top-2 rounded-[--radius-pill] bg-ink/75 px-2.5 py-1 font-ui text-[10px] uppercase tracking-[0.14em] text-cream">
          Placeholder
        </figcaption>
      ) : null}
    </figure>
  )
}

import { Head } from '@inertiajs/react'

export type SeoData = {
  title: string
  description: string
  image: string | null
  canonical: string
  noindex: boolean
  structured_data: Record<string, unknown> | null
}

/** Per-page meta, Open Graph and JSON-LD. */
export default function Seo({ seo }: { seo?: SeoData }) {
  if (!seo) return null

  return (
    <Head title={seo.title}>
      <meta name="description" content={seo.description} />
      <link rel="canonical" href={seo.canonical} />
      {seo.noindex ? <meta name="robots" content="noindex, nofollow" /> : null}

      <meta property="og:type" content="website" />
      <meta property="og:site_name" content="DizzyMali" />
      <meta property="og:title" content={seo.title} />
      <meta property="og:description" content={seo.description} />
      <meta property="og:url" content={seo.canonical} />
      {seo.image ? <meta property="og:image" content={seo.image} /> : null}

      <meta name="twitter:card" content={seo.image ? 'summary_large_image' : 'summary'} />
      <meta name="twitter:title" content={seo.title} />
      <meta name="twitter:description" content={seo.description} />

      {seo.structured_data ? (
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(seo.structured_data) }}
        />
      ) : null}
    </Head>
  )
}

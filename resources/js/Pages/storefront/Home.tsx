import { Head, Link } from '@inertiajs/react'
import { ButtonLink, Card, Eyebrow, Swatch } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import type { GarmentType } from '../../types'

type Block = {
  id: number
  type: string
  title: string | null
  payload: Record<string, unknown> | null
}

export default function Home({
  blocks,
  garmentTypes,
  featuredFabrics,
}: {
  blocks: Block[]
  garmentTypes: { data: GarmentType[] } | GarmentType[]
  featuredFabrics: Array<{ id: number; name: string; colour_hex: string | null }>
}) {
  const garments = Array.isArray(garmentTypes) ? garmentTypes : garmentTypes.data
  const hero = blocks.find((b) => b.type === 'hero')
  const testimonials = blocks.find((b) => b.type === 'testimonial')
  const quotes = (testimonials?.payload?.quotes ?? []) as Array<{
    body: string
    author: string
    location: string
  }>

  return (
    <StorefrontLayout>
      <Head title="Bespoke tailoring" />

      <section className="grid items-center gap-12 pb-20 lg:grid-cols-[1.1fr_0.9fr]">
        <div>
          <Eyebrow>{(hero?.payload?.eyebrow as string) ?? 'DizzyMali'}</Eyebrow>
          <h1 className="mt-4 font-display text-[clamp(2.6rem,7vw,5.1rem)] leading-[0.98]">
            {hero?.title ?? 'Commissioned,'}
            <br />
            <span className="accent-italic">not bought.</span>
          </h1>
          <p className="mt-6 max-w-lg text-[17px] leading-relaxed text-ink-muted">
            {(hero?.payload?.body as string) ??
              'Agbada, Kaftan, Jalabiya and Danshiki, cut to your measurements and sent anywhere in the world.'}
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <ButtonLink href="/order" variant="accent">
              Start your garment
            </ButtonLink>
            <ButtonLink href="/fabrics" variant="ghost">
              See the cloth
            </ButtonLink>
          </div>
        </div>

        <div className="grid grid-cols-2 gap-4">
          {featuredFabrics.slice(0, 4).map((fabric) => (
            <div
              key={fabric.id}
              className="flex aspect-[4/5] flex-col justify-end rounded-[--radius-card] border border-line p-5"
              style={{ backgroundColor: fabric.colour_hex ?? '#EFE7DC' }}
            >
              <span className="rounded-[--radius-pill] bg-cream/90 px-3 py-1 text-center font-ui text-[11px] uppercase tracking-[0.16em] text-ink">
                {fabric.name}
              </span>
            </div>
          ))}
        </div>
      </section>

      <section className="border-t border-line py-20">
        <Eyebrow>Four garments</Eyebrow>
        <h2 className="mt-3 font-display text-[clamp(1.9rem,4vw,2.9rem)]">Choose the occasion</h2>

        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {garments.map((g) => (
            <Link key={g.id} href={`/garments/${g.slug}`} className="group">
              <Card interactive className="flex h-full flex-col">
                <h3 className="font-display text-2xl">{g.name}</h3>
                <p className="mt-2 flex-1 text-[14px] text-ink-muted">{g.tagline}</p>
                <dl className="mt-5 space-y-1 border-t border-line pt-4 text-[13px]">
                  <div className="flex justify-between">
                    <dt className="text-ink-muted">Sewing from</dt>
                    <dd className="tabular">{g.sewing_cost.formatted}</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-ink-muted">Typical cloth</dt>
                    <dd className="tabular">{g.default_yardage} yd</dd>
                  </div>
                  <div className="flex justify-between">
                    <dt className="text-ink-muted">Lead time</dt>
                    <dd className="tabular">{g.lead_time_days} days</dd>
                  </div>
                </dl>
              </Card>
            </Link>
          ))}
        </div>
      </section>

      <section className="border-t border-line py-20">
        <div className="grid gap-10 lg:grid-cols-3">
          {[
            ['Measure once', 'Save your measurements and reorder in a single tap. Illustrations and plain-language guidance for every dimension.'],
            ['See the price working', 'Fabric by the yard, sewing, options and delivery, itemised before you pay. No surprises at checkout.'],
            ['Watch it being made', 'Photographs of your cloth at every stage, from the cutting table to the courier.'],
          ].map(([title, body]) => (
            <div key={title}>
              <div className="h-px w-12 bg-terracotta" />
              <h3 className="mt-5 font-display text-2xl">{title}</h3>
              <p className="mt-2 text-[15px] text-ink-muted">{body}</p>
            </div>
          ))}
        </div>
      </section>

      {quotes.length > 0 ? (
        <section className="border-t border-line py-20">
          <Eyebrow>From the diaspora</Eyebrow>
          <div className="mt-8 grid gap-6 md:grid-cols-2">
            {quotes.map((q) => (
              <Card key={q.author}>
                <p className="font-display text-xl leading-snug">“{q.body}”</p>
                <p className="mt-4 text-[13px] text-ink-muted">
                  {q.author} — {q.location}
                </p>
              </Card>
            ))}
          </div>
        </section>
      ) : null}

      <section className="flex flex-wrap items-center justify-between gap-6 rounded-[--radius-card] bg-ink px-8 py-12 text-cream">
        <div className="flex items-center gap-3">
          {featuredFabrics.slice(0, 5).map((f) => (
            <Swatch key={f.id} hex={f.colour_hex} size={32} />
          ))}
        </div>
        <div className="max-w-md">
          <h2 className="font-display text-3xl">Ready when you are.</h2>
          <p className="mt-2 text-[15px] text-[#A79D94]">Six steps, and you can stop and come back at any point.</p>
        </div>
        <ButtonLink href="/order" variant="accent">
          Begin
        </ButtonLink>
      </section>
    </StorefrontLayout>
  )
}

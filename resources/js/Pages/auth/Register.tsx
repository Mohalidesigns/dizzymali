import { Head, Link, useForm } from '@inertiajs/react'
import { Button, Card, Field, Input } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'

export default function Register() {
  const form = useForm({
    name: '',
    email: '',
    phone: '',
    country_code: '',
    password: '',
    password_confirmation: '',
    privacy_consent: false as boolean,
    whatsapp_opt_in: false,
    marketing_opt_in: false,
  })

  return (
    <StorefrontLayout>
      <Head title="Create an account" />
      <div className="mx-auto max-w-lg py-8">
        <h1 className="font-display text-4xl">Create an account</h1>
        <p className="mt-2 text-ink-muted">
          So your measurements are waiting for you the next time, and so you can follow your garment
          through the workshop.
        </p>

        <Card className="mt-8">
          <form
            onSubmit={(e) => {
              e.preventDefault()
              form.post('/register')
            }}
            className="space-y-5"
          >
            <Field label="Full name" required error={form.errors.name}>
              <Input
                autoComplete="name"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
              />
            </Field>

            <Field label="Email" required error={form.errors.email}>
              <Input
                type="email"
                autoComplete="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
              />
            </Field>

            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="Phone" hint="For WhatsApp updates, if you want them." error={form.errors.phone}>
                <Input
                  type="tel"
                  autoComplete="tel"
                  value={form.data.phone}
                  onChange={(e) => form.setData('phone', e.target.value)}
                />
              </Field>
              <Field label="Country" hint="Two letters, e.g. NG, GB, US." error={form.errors.country_code}>
                <Input
                  maxLength={2}
                  value={form.data.country_code}
                  onChange={(e) => form.setData('country_code', e.target.value.toUpperCase())}
                />
              </Field>
            </div>

            <Field label="Password" required hint="At least ten characters." error={form.errors.password}>
              <Input
                type="password"
                autoComplete="new-password"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
              />
            </Field>

            <Field label="Confirm password" required>
              <Input
                type="password"
                autoComplete="new-password"
                value={form.data.password_confirmation}
                onChange={(e) => form.setData('password_confirmation', e.target.value)}
              />
            </Field>

            <div className="space-y-3 border-t border-line pt-5">
              <label className="flex items-start gap-3 text-[14px]">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={form.data.privacy_consent}
                  onChange={(e) => form.setData('privacy_consent', e.target.checked)}
                />
                <span>
                  I agree to DizzyMali holding my measurements and any photographs I upload, for the
                  purpose of making my garments.{' '}
                  <span className="text-ink-muted">
                    You can export or delete this data at any time.
                  </span>
                </span>
              </label>
              {form.errors.privacy_consent ? (
                <p role="alert" className="text-[13px] text-danger">
                  {form.errors.privacy_consent}
                </p>
              ) : null}

              <label className="flex items-start gap-3 text-[14px] text-ink-muted">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={form.data.whatsapp_opt_in}
                  onChange={(e) => form.setData('whatsapp_opt_in', e.target.checked)}
                />
                Send me order updates on WhatsApp.
              </label>

              <label className="flex items-start gap-3 text-[14px] text-ink-muted">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={form.data.marketing_opt_in}
                  onChange={(e) => form.setData('marketing_opt_in', e.target.checked)}
                />
                Occasional news about new cloth. No more than monthly.
              </label>
            </div>

            <Button type="submit" className="w-full" disabled={form.processing}>
              {form.processing ? 'Creating…' : 'Create account'}
            </Button>
          </form>
        </Card>

        <p className="mt-6 text-center text-[14px]">
          Already have an account?{' '}
          <Link href="/login" className="text-accent-ink hover:underline">
            Sign in
          </Link>
        </p>
      </div>
    </StorefrontLayout>
  )
}

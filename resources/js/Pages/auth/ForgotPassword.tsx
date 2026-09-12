import { Head, useForm } from '@inertiajs/react'
import { Button, Card, Field, Input } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'

export default function ForgotPassword({ status }: { status?: string }) {
  const form = useForm({ email: '' })

  return (
    <StorefrontLayout>
      <Head title="Reset your password" />
      <div className="mx-auto max-w-md py-8">
        <h1 className="font-display text-4xl">Reset your password</h1>
        <p className="mt-2 text-ink-muted">We will email you a link.</p>
        {status ? <p className="mt-4 text-[14px] text-success">{status}</p> : null}

        <Card className="mt-8">
          <form
            onSubmit={(e) => {
              e.preventDefault()
              form.post('/forgot-password')
            }}
            className="space-y-5"
          >
            <Field label="Email" required error={form.errors.email}>
              <Input
                type="email"
                autoComplete="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
              />
            </Field>
            <Button type="submit" className="w-full" disabled={form.processing}>
              Send reset link
            </Button>
          </form>
        </Card>
      </div>
    </StorefrontLayout>
  )
}

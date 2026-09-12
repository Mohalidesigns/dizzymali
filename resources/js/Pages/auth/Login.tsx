import { Head, Link, useForm } from '@inertiajs/react'
import { Button, Card, ErrorNote, Field, Input } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'

export default function Login({ status }: { status?: string }) {
  const form = useForm({ email: '', password: '', remember: false })

  return (
    <StorefrontLayout>
      <Head title="Sign in" />
      <div className="mx-auto max-w-md py-8">
        <h1 className="font-display text-4xl">Welcome back</h1>
        <p className="mt-2 text-ink-muted">Pick up where you left off.</p>

        {status ? <p className="mt-4 text-[14px] text-success">{status}</p> : null}

        <Card className="mt-8">
          <form
            onSubmit={(e) => {
              e.preventDefault()
              form.post('/login')
            }}
            className="space-y-5"
          >
            {form.errors.email ? <ErrorNote>{form.errors.email}</ErrorNote> : null}

            <Field label="Email" required>
              <Input
                type="email"
                autoComplete="email"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
              />
            </Field>

            <Field label="Password" required error={form.errors.password}>
              <Input
                type="password"
                autoComplete="current-password"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
              />
            </Field>

            <label className="flex items-center gap-2 text-[14px] text-ink-muted">
              <input
                type="checkbox"
                checked={form.data.remember}
                onChange={(e) => form.setData('remember', e.target.checked)}
              />
              Keep me signed in
            </label>

            <Button type="submit" className="w-full" disabled={form.processing}>
              {form.processing ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>
        </Card>

        <div className="mt-6 flex justify-between text-[14px]">
          <Link href="/forgot-password" className="text-accent-ink hover:underline">
            Forgotten your password?
          </Link>
          <Link href="/register" className="text-accent-ink hover:underline">
            Create an account
          </Link>
        </div>
      </div>
    </StorefrontLayout>
  )
}

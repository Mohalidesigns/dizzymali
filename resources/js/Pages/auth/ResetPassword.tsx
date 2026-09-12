import { Head, useForm } from '@inertiajs/react'
import { Button, Card, Field, Input } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'

export default function ResetPassword({ token, email }: { token: string; email: string }) {
  const form = useForm({ token, email, password: '', password_confirmation: '' })

  return (
    <StorefrontLayout>
      <Head title="Choose a new password" />
      <div className="mx-auto max-w-md py-8">
        <h1 className="font-display text-4xl">Choose a new password</h1>

        <Card className="mt-8">
          <form
            onSubmit={(e) => {
              e.preventDefault()
              form.post('/reset-password')
            }}
            className="space-y-5"
          >
            <Field label="Email" required error={form.errors.email}>
              <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
            </Field>
            <Field label="New password" required hint="At least ten characters." error={form.errors.password}>
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
            <Button type="submit" className="w-full" disabled={form.processing}>
              Save new password
            </Button>
          </form>
        </Card>
      </div>
    </StorefrontLayout>
  )
}

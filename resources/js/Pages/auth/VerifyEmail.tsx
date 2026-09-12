import { Head, router } from '@inertiajs/react'
import { Button, Card } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <StorefrontLayout>
      <Head title="Verify your email" />
      <div className="mx-auto max-w-md py-8">
        <h1 className="font-display text-4xl">Check your inbox</h1>
        <p className="mt-2 text-ink-muted">
          We have sent a link to confirm your address. It keeps your measurements and order history
          yours alone.
        </p>

        {status === 'verification-link-sent' ? (
          <p className="mt-4 text-[14px] text-success">A new link is on its way.</p>
        ) : null}

        <Card className="mt-8">
          <Button onClick={() => router.post('/email/verification-notification')} className="w-full">
            Send it again
          </Button>
          <button
            type="button"
            onClick={() => router.post('/logout')}
            className="mt-4 w-full text-center text-[14px] text-ink-muted hover:text-ink"
          >
            Sign out
          </button>
        </Card>
      </div>
    </StorefrontLayout>
  )
}

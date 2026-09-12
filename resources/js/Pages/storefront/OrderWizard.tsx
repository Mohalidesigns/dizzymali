import { Head, router, usePage } from '@inertiajs/react'
import type { FormDataConvertible } from '@inertiajs/core'
import { useCallback, useMemo, useRef, useState } from 'react'
import PricePanel from '../../Components/wizard/PricePanel'
import StepDelivery from '../../Components/wizard/StepDelivery'
import StepFabric from '../../Components/wizard/StepFabric'
import StepGarment from '../../Components/wizard/StepGarment'
import StepInspiration from '../../Components/wizard/StepInspiration'
import StepMeasurements from '../../Components/wizard/StepMeasurements'
import StepReview from '../../Components/wizard/StepReview'
import { Button } from '../../Components/Ui'
import StorefrontLayout from '../../Layouts/StorefrontLayout'
import { cx } from '../../lib/format'
import type {
  Address,
  FabricVariant,
  GarmentType,
  MeasurementProfile,
  Order,
  PageProps,
} from '../../types'

type WizardPatch = Record<string, string | number | boolean | number[] | null | undefined>

const STEPS = ['Garment', 'Fabric', 'Measurements', 'Style', 'Delivery', 'Review'] as const

type Props = {
  order: { data: Order } | Order
  garmentTypes: { data: GarmentType[] } | GarmentType[]
  fabricVariants: { data: FabricVariant[] } | FabricVariant[]
  materials: Array<{ id: number; slug: string; name: string }>
  profiles: { data: MeasurementProfile[] } | MeasurementProfile[]
  addresses: Address[]
  unitPreference: 'in' | 'cm'
}

const unwrap = <T,>(value: { data: T } | T): T =>
  value && typeof value === 'object' && 'data' in (value as object)
    ? (value as { data: T }).data
    : (value as T)

export default function OrderWizard(props: Props) {
  const { errors } = usePage<PageProps & { errors: Record<string, string | string[]> }>().props

  const order = unwrap(props.order)
  const garmentTypes = unwrap(props.garmentTypes)
  const fabricVariants = unwrap(props.fabricVariants)
  const profiles = unwrap(props.profiles)

  const [step, setStep] = useState(Math.min(Math.max(order.wizard_step, 1), 6))
  const [unit, setUnit] = useState<'in' | 'cm'>(props.unitPreference)
  const [busy, setBusy] = useState(false)
  const [styleNotes, setStyleNotes] = useState(order.items[0]?.style_notes ?? '')
  const notesTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  const item = order.items[0] ?? null
  const garmentType = useMemo(
    () => garmentTypes.find((g) => g.id === item?.garment_type?.id) ?? null,
    [garmentTypes, item],
  )
  const selectedOptionIds = useMemo(
    () => (item?.options ?? []).map((o) => o.id).filter((id): id is number => id !== null),
    [item],
  )

  /** Every change round-trips to the server, which re-prices and saves the draft. */
  const patch = useCallback(
    (data: WizardPatch, then?: () => void) => {
      setBusy(true)
      router.patch(`/order/${order.id}`, data as Record<string, FormDataConvertible>, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => setBusy(false),
        onSuccess: () => then?.(),
      })
    },
    [order.id],
  )

  const goTo = (next: number) => {
    setStep(next)
    patch({ wizard_step: next })
  }

  const toggleOption = (groupId: number, optionId: number, allowsMultiple: boolean) => {
    const group = garmentType?.option_groups?.find((g) => g.id === groupId)
    const groupOptionIds = (group?.options ?? []).map((o) => o.id)

    const next = allowsMultiple
      ? selectedOptionIds.includes(optionId)
        ? selectedOptionIds.filter((id) => id !== optionId)
        : [...selectedOptionIds, optionId]
      : [...selectedOptionIds.filter((id) => !groupOptionIds.includes(id)), optionId]

    patch({ option_ids: next })
  }

  const onStyleNotes = (value: string) => {
    setStyleNotes(value)
    if (notesTimer.current) clearTimeout(notesTimer.current)
    notesTimer.current = setTimeout(() => patch({ style_notes: value }), 700)
  }

  const submitErrors = Array.isArray(errors?.order)
    ? (errors.order as string[])
    : errors?.order
      ? [errors.order as string]
      : []

  const canSubmit =
    Boolean(item?.garment_type) &&
    Boolean(item?.fabric_variant) &&
    Boolean(item?.measurement_profile_id) &&
    order.totals.total.minor > 0

  return (
    <StorefrontLayout>
      <Head title="Commission a garment" />

      <nav aria-label="Order steps" className="mb-10 overflow-x-auto">
        <ol className="flex min-w-max items-center gap-2">
          {STEPS.map((label, index) => {
            const n = index + 1
            const done = n < step

            return (
              <li key={label} className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => goTo(n)}
                  aria-current={step === n ? 'step' : undefined}
                  className={cx(
                    'flex items-center gap-2 rounded-[--radius-pill] px-4 py-2 font-ui text-[12px] uppercase tracking-[0.14em] transition-colors',
                    step === n
                      ? 'bg-ink text-cream'
                      : done
                        ? 'text-accent-ink hover:bg-sand'
                        : 'text-ink-muted hover:bg-sand',
                  )}
                >
                  <span className="tabular">{n}</span>
                  <span>{label}</span>
                </button>
                {n < STEPS.length ? <span className="h-px w-4 bg-line" aria-hidden="true" /> : null}
              </li>
            )
          })}
        </ol>
      </nav>

      <div className="grid gap-12 lg:grid-cols-[1fr_320px]">
        <div>
          {step === 1 ? (
            <StepGarment
              garmentTypes={garmentTypes}
              selectedId={item?.garment_type?.id ?? null}
              onSelect={(id) => patch({ garment_type_id: id }, () => goTo(2))}
            />
          ) : null}

          {step === 2 ? (
            <StepFabric
              variants={fabricVariants}
              materials={props.materials}
              selectedId={item?.fabric_variant?.id ?? null}
              onSelect={(id) => patch({ fabric_variant_id: id }, () => goTo(3))}
            />
          ) : null}

          {step === 3 ? (
            <StepMeasurements
              garmentType={garmentType}
              profiles={profiles}
              selectedProfileId={item?.measurement_profile_id ?? null}
              unit={unit}
              onUnitChange={setUnit}
              onSelectProfile={(id) => patch({ measurement_profile_id: id }, () => goTo(4))}
            />
          ) : null}

          {step === 4 ? (
            <StepInspiration
              garmentType={garmentType}
              item={item}
              selectedOptionIds={selectedOptionIds}
              styleNotes={styleNotes}
              onToggleOption={toggleOption}
              onStyleNotes={onStyleNotes}
            />
          ) : null}

          {step === 5 ? (
            <StepDelivery
              order={order}
              garmentType={garmentType}
              addresses={props.addresses}
              onChange={patch}
            />
          ) : null}

          {step === 6 ? <StepReview order={order} errors={submitErrors} /> : null}

          <div className="mt-12 flex items-center justify-between border-t border-line pt-6">
            <Button variant="ghost" disabled={step === 1} onClick={() => goTo(step - 1)}>
              Back
            </Button>
            {step < 6 ? (
              <Button onClick={() => goTo(step + 1)}>Continue</Button>
            ) : (
              <Button
                variant="accent"
                disabled={!canSubmit || busy}
                onClick={() => router.post(`/order/${order.id}/submit`)}
              >
                Place this order
              </Button>
            )}
          </div>
        </div>

        <PricePanel
          order={order}
          busy={busy}
          canSubmit={canSubmit}
          onSubmit={() => router.post(`/order/${order.id}/submit`)}
        />
      </div>
    </StorefrontLayout>
  )
}

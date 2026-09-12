export type Money = {
  minor: number
  currency: string
  formatted: string
}

export type AuthUser = {
  id: number
  name: string
  email: string
  unit_preference: 'in' | 'cm'
  preferred_currency: string
  country_code: string | null
  is_back_office: boolean
  is_admin: boolean
  roles: string[]
}

export type PageProps = {
  auth: { user: AuthUser | null }
  currencies: Array<{ code: string; symbol: string; decimals: number }>
  flash: { success: string | null; error: string | null }
}

export type MeasurementField = {
  id: number
  key: string
  group: 'top' | 'trouser'
  label: string
  help_text: string | null
  unit_type: 'length' | 'circumference'
  min_inches: number
  max_inches: number
  is_required: boolean
}

export type GarmentOptionGroup = {
  id: number
  slug: string
  name: string
  help_text: string | null
  is_required: boolean
  allows_multiple: boolean
  options: Array<{
    id: number
    slug: string
    name: string
    description: string | null
    surcharge: Money
    additional_yards: number
    additional_lead_days: number
    is_default: boolean
  }>
}

export type GarmentType = {
  id: number
  slug: string
  name: string
  tagline: string | null
  description: string | null
  default_yardage: number
  lead_time_days: number
  requires_top_measurements: boolean
  requires_trouser_measurements: boolean
  sewing_cost: Money
  measurement_fields?: MeasurementField[]
  option_groups?: GarmentOptionGroup[]
}

export type FabricVariant = {
  id: number
  sku: string
  colour_name: string
  colour_hex: string | null
  pattern: string | null
  price_per_yard: Money
  available_yards: number
  is_low_stock: boolean
  min_order_yards: number
  fabric?: {
    id: number
    slug: string
    name: string
    gsm: number | null
    width_inches: number | null
    origin: string | null
    care_instructions: string | null
    drape_notes: string | null
    material: { id: number; slug: string; name: string } | null
  }
}

export type MeasurementProfile = {
  id: number
  name: string
  unit_preference: 'in' | 'cm'
  source: string
  review_status: string
  is_default: boolean
  notes: string | null
  verified_at: string | null
  updated_at: string | null
  values?: Array<{
    key: string
    label: string
    group: 'top' | 'trouser'
    inches: number
    cm: number
  }>
}

export type Address = {
  id: number
  label: string | null
  recipient_name: string
  phone: string
  line_1: string
  line_2: string | null
  city: string
  state_region: string | null
  postcode: string | null
  country_code: string
  is_default: boolean
}

export type OrderItem = {
  id: number
  quantity: number
  garment_type: { id: number; slug?: string; name: string } | null
  fabric_variant: {
    id: number
    name: string
    colour_hex?: string | null
    price_per_yard_kobo?: number
  } | null
  yards: {
    base: number
    size_adjustment: number
    customer_extra: number
    total: number
  }
  costs: {
    fabric: Money
    sewing: Money
    options: Money
    line_total: Money
  }
  style_notes: string | null
  measurement_profile_id: number | null
  measurements: Array<{ key: string; label: string; group: string; value_inches: number }>
  options: Array<{ id: number | null; group: string; name: string; surcharge: Money }>
  inspirations: Array<{ id: number; url: string; original_name: string | null }>
}

export type Order = {
  id: number
  reference: string
  status: string
  status_label: string
  wizard_step: number
  is_editable: boolean
  service_level: 'standard' | 'express'
  customer_notes: string | null
  currency_code: string
  totals: {
    fabric: Money
    sewing: Money
    options: Money
    subtotal: Money
    shipping: Money
    discount: Money
    total: Money
    paid: Money
    balance_due: Money
    display_total: Money | null
  }
  timeline: {
    current: string
    current_label: string
    current_description: string
    position: number
    stages: Array<{ key: string; label: string; description: string; reached: boolean }>
  }
  promised_at: string | null
  placed_at: string | null
  submitted_at: string | null
  is_overdue: boolean
  shipping_address: Record<string, string | null> | null
  items: OrderItem[]
  events?: Array<{ id: number; to_status: string; label: string; stage: string; note: string | null; at: string | null }>
  progress_photos?: Array<{ id: number; stage: string; caption: string | null; alt_text: string; at: string | null }>
}

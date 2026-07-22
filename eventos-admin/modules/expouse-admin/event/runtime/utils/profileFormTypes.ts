/**
 * Appearance of the shared / embedded public form (`forms.settings.design`).
 * `brand_color` null means "inherit the event's primary colour", so a form
 * left alone keeps following a rebrand.
 */
export interface FormDesign {
  background_type: 'color' | 'image'
  background_color: string
  background_image_url: string | null
  brand_color: string | null
  card_style: 'solid' | 'glass'
  show_header: boolean
}

export const DEFAULT_FORM_DESIGN: FormDesign = {
  background_type: 'color',
  background_color: '#f1f3f9',
  background_image_url: null,
  brand_color: null,
  card_style: 'solid',
  show_header: true,
}

/** Client-side field model the profile form builder edits (Event Settings › Profile). */
export interface BuilderField {
  /** local identity for v-for / selection — never sent to the server */
  _id: string
  /** stable data key; empty until first save for newly added fields */
  key: string
  label: string
  help_text: string
  type: string
  is_default: boolean
  is_required: boolean
  is_unique: boolean
  is_pii: boolean
  validation: Record<string, any> | null
  meta: {
    placeholder?: string
    width?: number
    visible?: boolean
    show_to_others?: boolean
    surfaces?: { registration?: boolean, onboarding?: boolean, public?: boolean }
  }
  options: { label: string, value?: string }[]
}

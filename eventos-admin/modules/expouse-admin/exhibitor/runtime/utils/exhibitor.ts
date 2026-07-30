// Exhibitor feature — shared types, constants and pure helpers.
// Everything here is auto-imported by Nuxt (utils/ directory).

// ── Domain types ───────────────────────────────────────────────────────────
// What the API actually returns. These replaced a wall of `any`: the tables and
// drawers all read these fields, so a rename on the server should break the
// build here rather than render a silently empty cell.

export interface ExhibitorPackage {
  id: number | string
  name: string
  entitlements?: FeatureLine[] | null
}

/** One of the event's "Manage Filters" definitions — same shape the Showcase ›
 *  Filters screen writes (event settings JSON), reused by the Details tab. */
export interface FilterHeading { heading: string; mandatory: boolean; options: string[] }
export interface EventFilter { id: string; title: string; headings: FilterHeading[] }

export interface ExhibitorMemberContact { name?: string; email?: string; can_login?: boolean }
export interface ExhibitorMember { id: number; role: string; contact?: ExhibitorMemberContact }
export interface ExhibitorDocument { id: number; title: string; url: string }
/** Rich project fields kept in ExhibitorProject.meta (jsonb) — same builder
 *  pattern as products (image, CTA button, attachment). */
export interface ProjectMeta {
  image_url?: string
  image_file_id?: number | null
  button_label?: string
  button_url?: string
  attachment_url?: string
  attachment_name?: string
  attachment_file_id?: number | null
}
export interface ExhibitorProject { id: number; name: string; description?: string; status?: string; meta?: ProjectMeta }
/** Rich product fields kept in ExhibitorProduct.meta (jsonb) — no dedicated
 *  columns, matching the "Add Product" builder form. */
export interface ProductMeta {
  image_url?: string
  image_file_id?: number | null
  button_label?: string
  button_url?: string
  attachment_url?: string
  attachment_name?: string
  attachment_file_id?: number | null
  is_job_offer?: boolean
}
export interface ExhibitorProduct { id: number; name: string; description?: string; price_cents: number | null; meta?: ProductMeta }

/** A row in the exhibitors table. The edit drawer additionally loads the
 *  sub-resources below, which the list endpoint does not return. */
export interface Exhibitor {
  id: string
  name: string
  email?: string
  type?: string
  status?: 'active' | 'suspended'
  logo_url?: string
  logo_file_id?: number | null
  package_id?: number | string | null
  stall_no?: string
  members_count?: number
  team_limit?: number
  phone_code?: string
  phone?: string
  rating?: boolean
  featured?: boolean
  premium?: boolean
  about?: string
  venue?: string; street?: string; address_line1?: string; address_line2?: string
  city?: string; state?: string; zip?: string; country?: string
  location_url?: string; website_url?: string
  tags?: string[]
  filter_id?: string
  filter_selections?: Record<string, Record<string, string[]>>
  spotlight_type?: 'image' | 'video'
  spotlight_url?: string
  spotlight_file_id?: number | null
  cta?: CtaItem[]
  social?: Partial<Social>
  contact?: Partial<Contact>
  entitlements?: FeatureLine[]
  package?: ExhibitorPackage | null
  members?: ExhibitorMember[]
  documents?: ExhibitorDocument[]
  projects?: ExhibitorProject[]
  products?: ExhibitorProduct[]
}

/** Credentials revealed after an auto password reset. */
export interface ResetResult { email: string; password: string }

export type CtaType = 'text' | 'image' | 'video'
export interface CtaVideo { platform: string; url: string; caption: string }
/** Exhibitor CTA — same shapes as event Communication › CTA (text / image / video). */
export interface CtaItem {
  id: string
  type: CtaType
  title: string
  description: string
  button_label: string
  button_link: string
  image_url: string
  image_file_id: number | null
  videos: CtaVideo[]
  /** Accordion open state in the editor — UI only, stripped on save. */
  open: boolean
}
export interface Social { facebook: string; linkedin: string; twitter: string; instagram: string; whatsapp: string; youtube: string }
export const CTA_TYPES: { value: CtaType; label: string }[] = [
  { value: 'text', label: 'Text' },
  { value: 'image', label: 'Image' },
  { value: 'video', label: 'Video' },
]
export const CTA_VIDEO_PLATFORMS = ['Youtube', 'Vimeo', 'Facebook', 'Other']

export function freshCta(type: CtaType = 'text'): CtaItem {
  return {
    id: `cta_${Date.now()}`,
    type,
    title: '',
    description: '',
    button_label: '',
    button_link: '',
    image_url: '',
    image_file_id: null,
    videos: [],
    open: true,
  }
}

/** Coerce API / legacy CTA rows (TEXT/LINK/BUTTON) into the current shape. */
export function normalizeCta(raw: Record<string, unknown> | CtaItem): CtaItem {
  const blank = freshCta()
  const t = String((raw as CtaItem).type || 'text').toLowerCase()
  const type: CtaType = t === 'image' || t === 'video' || t === 'text' ? t : 'text'
  const videos = Array.isArray((raw as CtaItem).videos)
    ? (raw as CtaItem).videos.map(v => ({
        platform: v.platform || 'Youtube',
        url: v.url || '',
        caption: v.caption || '',
      }))
    : []
  // Legacy TEXT/LINK/BUTTON used label/value.
  const legacy = raw as { label?: string; value?: string }
  return {
    ...blank,
    id: String((raw as CtaItem).id || blank.id),
    type,
    title: String((raw as CtaItem).title || legacy.label || ''),
    description: String((raw as CtaItem).description || (type === 'text' ? legacy.value || '' : '')),
    button_label: String((raw as CtaItem).button_label || (t === 'button' ? legacy.label || '' : '')),
    button_link: String((raw as CtaItem).button_link || (t === 'link' || t === 'button' ? legacy.value || '' : '')),
    image_url: String((raw as CtaItem).image_url || ''),
    image_file_id: (raw as CtaItem).image_file_id ?? null,
    videos,
    open: false,
  }
}
export interface Contact { full_name: string; company_name: string; position: string; email: string; phone_code: string; phone: string }
export interface FeatureLine { key: string; enabled: boolean; limit: number }
export interface Draft {
  name: string; email: string; logo_url: string; logo_file_id: number | null
  package_id: number | string; stall_no: string; type: string
  phone_code: string; phone: string
  rating: boolean; featured: boolean; premium: boolean
  about: string
  venue: string; street: string; address_line1: string; address_line2: string
  city: string; state: string; zip: string; country: string
  location_url: string; website_url: string
  tags: string[]; filter_id: string
  // Selections against the event's "Manage Filters": filterId → heading → chosen options.
  filter_selections: Record<string, Record<string, string[]>>
  spotlight_type: 'image' | 'video'; spotlight_url: string; spotlight_file_id: number | null
  cta: CtaItem[]; social: Social; contact: Contact
}

export { PHONE_CODES, findPhoneCode, type PhoneCode } from '../../../core/runtime/utils/phoneCodes'
import { PHONE_CODES } from '../../../core/runtime/utils/phoneCodes'
export const TYPE_OPTIONS  = ['Exhibitor', 'Sponsor']
export const STALL_OPTIONS = ['A1','A2','A3','B1','B2','B3','C1','C2','C3','D1','D2','D3']
export const COUNTRIES     = ['Bangladesh','United States','United Kingdom','UAE','India','Saudi Arabia','Qatar','Kuwait','Singapore','Malaysia','Canada','Australia']
export const EXHIBITOR_TABS = ['Details','Teams','Documents','Projects','Products','Permissions']
export const EXHIBITOR_LIMIT = 50

/** ISO-2 → COUNTRIES label (location select). Dial code comes from PHONE_CODES. */
const COUNTRY_BY_ISO: Record<string, string> = {
  BD: 'Bangladesh',
  US: 'United States',
  GB: 'United Kingdom',
  AE: 'UAE',
  IN: 'India',
  SA: 'Saudi Arabia',
  QA: 'Qatar',
  SG: 'Singapore',
  MY: 'Malaysia',
  KW: 'Kuwait',
  CA: 'Canada',
  AU: 'Australia',
}

const GEO_CACHE_KEY = 'exhibitor_geo_from_ip'
let geoInflight: Promise<{ phone_code: string; country: string } | null> | null = null

/**
 * Detect phone dial code + country from the visitor's public IP.
 * Cached in-memory and sessionStorage; fails soft (returns null).
 */
export async function detectLocaleFromIp(): Promise<{ phone_code: string; country: string } | null> {
  if (import.meta.server) return null
  try {
    const cached = sessionStorage.getItem(GEO_CACHE_KEY)
    if (cached) {
      const parsed = JSON.parse(cached) as { phone_code: string; country: string }
      if (parsed?.phone_code && parsed?.country) return parsed
    }
  } catch { /* ignore */ }

  if (geoInflight) return geoInflight

  geoInflight = (async () => {
    try {
      const res = await fetch('https://ipwho.is/', { signal: AbortSignal.timeout(4000) })
      if (!res.ok) return null
      const data = await res.json() as { success?: boolean; country_code?: string }
      if (!data?.success || !data.country_code) return null
      const iso = data.country_code.toUpperCase()
      const phone = PHONE_CODES.find(p => p.iso === iso)
      if (!phone) return null
      const locale = {
        phone_code: phone.code,
        country: COUNTRY_BY_ISO[iso] || phone.name,
      }
      try { sessionStorage.setItem(GEO_CACHE_KEY, JSON.stringify(locale)) } catch { /* ignore */ }
      return locale
    } catch {
      return null
    } finally {
      geoInflight = null
    }
  })()

  return geoInflight
}

// Blank "add" forms for the edit-drawer tabs. They double as the reset value
// after a successful add (see useExhibitorCollection).
export const MEMBER_FORM = { email: '', first_name: '', last_name: '', role: 'staff', password: '' }
export const DOC_FORM = { title: '', url: '', file_id: null as number | null }
export const PROJECT_FORM = {
  name: '', description: '', status: '',
  image_url: '', image_file_id: null as number | null,
  button_label: '', button_url: '',
  attachment_url: '', attachment_name: '', attachment_file_id: null as number | null,
}
/** A filled-in "Add Project" form, as the builder hands it back on submit. */
export type ProjectDraft = typeof PROJECT_FORM
export const PRODUCT_FORM = {
  name: '', description: '', price: '',
  image_url: '', image_file_id: null as number | null,
  button_label: '', button_url: '',
  attachment_url: '', attachment_name: '', attachment_file_id: null as number | null,
  is_job_offer: false,
}

// `countable: false` → on/off toggle only (no quantity limit stepper).
// Keep the Leads keys in sync with the Exhibitor Packages catalogue
// (showcase/packages.vue) so entitlements and package defaults line up.
export const ALL_FEATURES: { key: string; label: string; countable?: boolean }[] = [
  { key: 'teams',             label: 'Teams' },
  { key: 'projects',          label: 'Projects' },
  { key: 'products',          label: 'Products' },
  { key: 'documents',         label: 'Documents' },
  { key: 'videos',            label: 'Videos' },
  { key: 'cta',               label: 'CTA' },
  { key: 'meetings',          label: 'Meetings' },
  { key: 'lounge',            label: 'Lounge' },
  // Leads — on/off only.
  { key: 'all_leads',          label: 'All Leads',          countable: false },
  { key: 'team_connections',   label: 'Team Connections',   countable: false },
  { key: 'recommended_leads',  label: 'Recommended Leads',  countable: false },
  { key: 'lead_qualification', label: 'Lead Qualification', countable: false },
  { key: 'lead_analytics',     label: 'Leads Analytics',    countable: false },
  { key: 'lead_export',        label: 'Lead Export',        countable: false },
  { key: 'analytics',         label: 'Analytics',          countable: false },
]

/** Whether a feature carries a numeric limit (vs a plain on/off toggle). */
export function featureCountable(key: string) {
  return ALL_FEATURES.find(f => f.key === key)?.countable !== false
}

export function freshDraft(): Draft {
  return {
    name: '', email: '', logo_url: '', logo_file_id: null,
    package_id: '', stall_no: '', type: '',
    phone_code: '+880', phone: '',
    rating: false, featured: false, premium: false,
    about: '',
    venue: '', street: '', address_line1: '', address_line2: '',
    city: '', state: '', zip: '', country: '',
    location_url: '', website_url: '',
    tags: [], filter_id: '', filter_selections: {},
    spotlight_type: 'image', spotlight_url: '', spotlight_file_id: null,
    cta: [],
    social: { facebook: '', linkedin: '', twitter: '', instagram: '', whatsapp: '', youtube: '' },
    contact: { full_name: '', company_name: '', position: '', email: '', phone_code: '+880', phone: '' },
  }
}

export function featureLabel(key: string) {
  return ALL_FEATURES.find(f => f.key === key)?.label ?? key
}

/** Collect the builder fields of the project form into its meta payload. */
export function projectMeta(f: ProjectDraft): ProjectMeta {
  return {
    image_url: f.image_url || undefined,
    image_file_id: f.image_file_id ?? undefined,
    button_label: f.button_label || undefined,
    button_url: f.button_url || undefined,
    attachment_url: f.attachment_url || undefined,
    attachment_name: f.attachment_name || undefined,
    attachment_file_id: f.attachment_file_id ?? undefined,
  }
}

/** Collect the builder fields of the product form into its meta payload. */
export function productMeta(f: typeof PRODUCT_FORM): ProductMeta {
  return {
    image_url: f.image_url || undefined,
    image_file_id: f.image_file_id ?? undefined,
    button_label: f.button_label || undefined,
    button_url: f.button_url || undefined,
    attachment_url: f.attachment_url || undefined,
    attachment_name: f.attachment_name || undefined,
    attachment_file_id: f.attachment_file_id ?? undefined,
    is_job_offer: f.is_job_offer,
  }
}

/**
 * Ids arrive as numbers from the API and as strings from <select> bindings, so
 * every comparison used to be a loose `==` with an eslint-disable on top.
 * Compare them as strings once, here.
 */
export function sameId(a: unknown, b: unknown): boolean {
  return a != null && b != null && String(a) === String(b)
}

/** The message an API error carries, or a sensible fallback. */
export function exhibitorError(e: unknown, fallback: string): string {
  return (e as { data?: { message?: string } })?.data?.message || fallback
}

/** Structured-clone a plain value — payloads must not carry Vue proxies. */
function plain<T>(value: T): T {
  return JSON.parse(JSON.stringify(value))
}

/** API row → editable draft. Every field is defaulted, so a sparse row from the
 *  server can never leave the form bound to `undefined`. */
export function draftFromExhibitor(e: Exhibitor | null | undefined): Draft {
  const blank = freshDraft()
  if (!e) return blank

  return {
    ...blank,
    name: e.name || '',
    email: e.email || '',
    logo_url: e.logo_url || '',
    logo_file_id: e.logo_file_id ?? null,
    package_id: e.package_id ?? '',
    stall_no: e.stall_no || '',
    // The API stores the type lowercase; the <select> options are capitalised.
    type: e.type ? e.type.charAt(0).toUpperCase() + e.type.slice(1) : 'Exhibitor',
    phone_code: e.phone_code || '+880',
    phone: e.phone || '',
    rating: !!e.rating,
    featured: !!e.featured,
    premium: !!e.premium,
    about: e.about || '',
    venue: e.venue || '',
    street: e.street || '',
    address_line1: e.address_line1 || '',
    address_line2: e.address_line2 || '',
    city: e.city || '',
    state: e.state || '',
    zip: e.zip || '',
    country: e.country || '',
    location_url: e.location_url || '',
    website_url: e.website_url || '',
    tags: Array.isArray(e.tags) ? [...e.tags] : [],
    filter_id: e.filter_id || '',
    filter_selections: e.filter_selections ? plain(e.filter_selections) : {},
    spotlight_type: e.spotlight_type || 'image',
    spotlight_url: e.spotlight_url || '',
    spotlight_file_id: e.spotlight_file_id ?? null,
    cta: Array.isArray(e.cta) ? e.cta.filter(Boolean).map(c => normalizeCta(c as CtaItem)) : [],
    social: { ...blank.social, ...(e.social || {}) },
    contact: { ...blank.contact, ...(e.contact || {}) },
  }
}

/** Draft → create/update payload. */
export function draftToPayload(draft: Draft, eventId: string) {
  return {
    event: eventId,
    name: draft.name,
    email: draft.email,
    logo_file_id: draft.logo_file_id,
    package_id: draft.package_id,
    stall_no: draft.stall_no,
    type: (draft.type || 'Exhibitor').toLowerCase(),
    phone_code: draft.phone_code,
    phone: draft.phone,
    rating: draft.rating,
    featured: draft.featured,
    premium: draft.premium,
    about: draft.about,
    venue: draft.venue,
    street: draft.street,
    address_line1: draft.address_line1,
    address_line2: draft.address_line2,
    city: draft.city,
    state: draft.state,
    zip: draft.zip,
    country: draft.country,
    location_url: draft.location_url,
    website_url: draft.website_url,
    tags: draft.tags,
    filter_id: draft.filter_id,
    filter_selections: plain(draft.filter_selections),
    spotlight_type: draft.spotlight_type,
    spotlight_url: draft.spotlight_url,
    spotlight_file_id: draft.spotlight_file_id,
    // Drop accordion `open` — UI-only.
    cta: draft.cta.map(({ open: _open, ...rest }) => plain(rest)),
    social: plain(draft.social),
    contact: plain(draft.contact),
  }
}

export function mergeFeatures(saved: FeatureLine[] | null | undefined): FeatureLine[] {
  const map = new Map((saved ?? []).map(f => [f.key, f]))
  return ALL_FEATURES.map((f) => {
    const s = map.get(f.key)
    return s ? { key: f.key, enabled: !!s.enabled, limit: Number(s.limit ?? 1) } : { key: f.key, enabled: false, limit: 1 }
  })
}

/** Full Showcase access — matches auth.hasFeature(null) = allow everything. */
export function allEnabledFeatures(): FeatureLine[] {
  return ALL_FEATURES.map(f => ({
    key: f.key,
    enabled: true,
    limit: featureCountable(f.key) ? 1 : 0,
  }))
}

/** True when the exhibitor has its own saved FeatureLine[] (even if all off). */
export function hasSavedEntitlements(saved: FeatureLine[] | null | undefined): boolean {
  return Array.isArray(saved) && saved.length > 0
}

/**
 * Permissions for this exhibitorId:
 *  1. Package entitlements as the base (when present)
 *  2. Booth Permissions overlay per key (including explicit off)
 *  3. Else full access (never configured → exhibitor can use everything)
 *
 * Keys missing from an older booth freeze still come from the package, so
 * newer catalogue entries (Leads & analytics) are not dropped.
 */
export function resolveEntitlements(
  exhibitor: Pick<Exhibitor, 'entitlements' | 'package_id' | 'package'>,
  packages: ExhibitorPackage[] = [],
): FeatureLine[] {
  const pkg = exhibitor.package
    ?? packages.find(p => String(p.id) === String(exhibitor.package_id ?? ''))

  const saved = exhibitor.entitlements
  const fromPackage = pkg?.entitlements
  const hasSaved = hasSavedEntitlements(saved)
  const hasPackage = hasSavedEntitlements(fromPackage)

  if (!hasSaved && !hasPackage) {
    return allEnabledFeatures()
  }

  const byKey = new Map<string, FeatureLine>()
  if (hasPackage) {
    for (const f of fromPackage!) {
      if (f?.key) byKey.set(f.key, f)
    }
  }
  if (hasSaved) {
    for (const f of saved!) {
      if (f?.key) byKey.set(f.key, f)
    }
  }

  return mergeFeatures([...byKey.values()])
}

export function exhibitorMoney(cents: number | null) {
  return cents != null ? '$' + (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2 }) : '—'
}

export function exhibitorInitials(name: string) {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  return (parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? parts[0]?.[1] ?? '')
}

export function exhibitorStatusLabel(e: Pick<Exhibitor, 'status'>) {
  const s = e.status || 'active'
  return s.charAt(0).toUpperCase() + s.slice(1)
}

export function isActive(e: Pick<Exhibitor, 'status'>) {
  return (e.status || 'active') === 'active'
}

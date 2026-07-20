// Exhibitor feature — shared types, constants and pure helpers.
// Everything here is auto-imported by Nuxt (utils/ directory).

// ── Domain types ───────────────────────────────────────────────────────────
// What the API actually returns. These replaced a wall of `any`: the tables and
// drawers all read these fields, so a rename on the server should break the
// build here rather than render a silently empty cell.

export interface ExhibitorPackage { id: number | string; name: string }

/** One of the event's "Manage Filters" definitions — same shape the Showcase ›
 *  Filters screen writes (event settings JSON), reused by the Details tab. */
export interface FilterHeading { heading: string; mandatory: boolean; options: string[] }
export interface EventFilter { id: string; title: string; headings: FilterHeading[] }

export interface ExhibitorMemberContact { name?: string; email?: string; can_login?: boolean }
export interface ExhibitorMember { id: number; role: string; contact?: ExhibitorMemberContact }
export interface ExhibitorDocument { id: number; title: string; url: string }
export interface ExhibitorProject { id: number; name: string; description?: string; status?: string }
export interface ExhibitorProduct { id: number; name: string; description?: string; price_cents: number | null }

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
  members?: ExhibitorMember[]
  documents?: ExhibitorDocument[]
  projects?: ExhibitorProject[]
  products?: ExhibitorProduct[]
}

/** Credentials revealed after an auto password reset. */
export interface ResetResult { email: string; password: string }

export interface CtaItem { id: string; type: string; label: string; value: string; open: boolean }
export interface Social { facebook: string; linkedin: string; twitter: string; instagram: string; whatsapp: string; youtube: string }
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

export const PHONE_CODES = [
  { code: '+880', flag: '🇧🇩' }, { code: '+1',   flag: '🇺🇸' }, { code: '+44',  flag: '🇬🇧' },
  { code: '+971', flag: '🇦🇪' }, { code: '+91',  flag: '🇮🇳' }, { code: '+966', flag: '🇸🇦' },
  { code: '+974', flag: '🇶🇦' }, { code: '+65',  flag: '🇸🇬' }, { code: '+60',  flag: '🇲🇾' },
]
export const TYPE_OPTIONS  = ['Exhibitor', 'Sponsor']
export const STALL_OPTIONS = ['A1','A2','A3','B1','B2','B3','C1','C2','C3','D1','D2','D3']
export const COUNTRIES     = ['Bangladesh','United States','United Kingdom','UAE','India','Saudi Arabia','Qatar','Kuwait','Singapore','Malaysia','Canada','Australia']
export const EXHIBITOR_TABS = ['Details','Members','Documents','Projects','Products','Permissions']
export const EXHIBITOR_LIMIT = 50

// Blank "add" forms for the edit-drawer tabs. They double as the reset value
// after a successful add (see useExhibitorCollection).
export const MEMBER_FORM = { email: '', first_name: '', last_name: '', role: 'staff', password: '' }
export const DOC_FORM = { title: '', url: '' }
export const PROJECT_FORM = { name: '', description: '', status: '' }
export const PRODUCT_FORM = { name: '', description: '', price: '' }

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
    package_id: '', stall_no: '', type: 'Exhibitor',
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
export function draftFromExhibitor(e: Exhibitor): Draft {
  const blank = freshDraft()

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
    // `open` drives the accordion in the CTA editor; it is UI state, not data.
    cta: Array.isArray(e.cta) ? e.cta.map(c => ({ ...c, open: false })) : [],
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
    type: draft.type.toLowerCase(),
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
    cta: plain(draft.cta),
    social: plain(draft.social),
    contact: plain(draft.contact),
  }
}

export function mergeFeatures(saved: FeatureLine[] | null): FeatureLine[] {
  const map = new Map((saved ?? []).map(f => [f.key, f]))
  return ALL_FEATURES.map((f) => {
    const s = map.get(f.key)
    return s ? { key: f.key, enabled: !!s.enabled, limit: Number(s.limit ?? 1) } : { key: f.key, enabled: false, limit: 1 }
  })
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

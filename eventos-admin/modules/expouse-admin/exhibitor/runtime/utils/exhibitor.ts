// Exhibitor feature — shared types, constants and pure helpers.
// Everything here is auto-imported by Nuxt (utils/ directory).

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
  street: string; city: string; state: string; zip: string; country: string
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
    street: '', city: '', state: '', zip: '', country: '',
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

export function exhibitorStatusLabel(p: any) {
  const s = p.status || 'active'
  return s.charAt(0).toUpperCase() + s.slice(1)
}

import { defineStore } from 'pinia'

export interface ExhibitorProduct {
  id: number
  name: string
  description: string | null
  price: number | null
  image_url: string | null
}

export interface ExhibitorDoc {
  id: number
  title: string
  url: string | null
}

export interface Exhibitor {
  id: string
  name: string
  type: 'exhibitor' | 'sponsor'
  category: string
  description: string
  website: string | null
  booth: string | null
  tier_rank: number
  is_featured: boolean
  logo_url: string | null
  social: Record<string, string>
  products: ExhibitorProduct[]
  documents: ExhibitorDoc[]
  // filterId → heading → chosen options (matched against EventFilter facets).
  filter_selections: Record<string, Record<string, string[]>>
}

export interface EventFilterHeading {
  heading: string
  options: string[]
}

export interface EventFilter {
  id: string
  title: string
  headings: EventFilterHeading[]
}

interface ExhibitorsPayload {
  exhibitors: Exhibitor[]
  sponsors: Exhibitor[]
  categories: string[]
  filters: EventFilter[]
  year: number | null
}

export interface ExhibitorMemberCard {
  name: string
  designation: string
  company: string | null
  avatar_url: string | null
}

export interface ExhibitorProject {
  name: string
  description: string | null
  image_url: string | null
}

export interface ExhibitorCta {
  id?: string
  type: string
  label: string
  value: string
}

/** Full booth profile for the details page (GET /public/exhibitors/{id}). */
export interface ExhibitorDetail extends Exhibitor {
  about: string
  can_rate: boolean
  spotlight: { type: 'image' | 'video', url: string | null }
  contact: { phone: string | null, email: string | null, full_name: string | null, position: string | null, company_name: string | null }
  cta: ExhibitorCta[]
  location: { address: string | null, url: string | null }
  members: ExhibitorMemberCard[]
  projects: ExhibitorProject[]
}

/**
 * The public exhibitor & sponsor directory ("Exhibitors" tab) for the event
 * this subdomain resolves to. Mirrors stores/speakers.ts — a single public GET
 * scoped to the subdomain; type/category/sort/search filtering is client-side
 * over the full list. `selected` drives the detail modal.
 */
export const useExhibitorsStore = defineStore('exhibitors', {
  state: () => ({
    exhibitors: [] as Exhibitor[],
    sponsors: [] as Exhibitor[],
    categories: [] as string[],
    filters: [] as EventFilter[],
    year: null as number | null,
    loading: false,
    loaded: false,
    error: false,
    selected: null as Exhibitor | null,

    // Details page (pages/exhibitor/[id].vue).
    detail: null as ExhibitorDetail | null,
    detailLoading: false,
    detailError: false,
  }),

  getters: {
    /** Every booth (exhibitors + sponsors) in one list for the "All" filter. */
    all: (s): Exhibitor[] => [...s.exhibitors, ...s.sponsors],
  },

  actions: {
    async fetchExhibitors() {
      const sub = useEventSubdomain()
      if (!sub) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: ExhibitorsPayload }>(`${apiBase}/public/exhibitors`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.exhibitors = res.data.exhibitors
        this.sponsors = res.data.sponsors
        this.categories = res.data.categories
        this.filters = res.data.filters ?? []
        this.year = res.data.year ?? null
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    open(exhibitor: Exhibitor) { this.selected = exhibitor },
    close() { this.selected = null },

    /** Load one booth's full profile for the details page. */
    async fetchDetail(id: string) {
      const sub = useEventSubdomain()
      if (!sub) { this.detailError = true; return }

      this.detailLoading = true
      this.detailError = false
      this.detail = null
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: ExhibitorDetail }>(`${apiBase}/public/exhibitors/${id}`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.detail = res.data
      } catch {
        this.detailError = true
      } finally {
        this.detailLoading = false
      }
    },
  },
})

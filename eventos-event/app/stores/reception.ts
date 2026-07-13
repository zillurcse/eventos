import { defineStore } from 'pinia'

export interface ReceptionSpeaker {
  id: string
  name: string | null
  designation: string
  company: string
  category: string
  image_url: string | null
}

export interface ReceptionPartner {
  id: string
  name: string
  type: string
  website: string | null
  booth: string | null
  logo_url: string | null
}

export interface ReceptionSession {
  id: string
  title: string
  description: string | null
  starts_at: string | null
  ends_at: string | null
  timezone: string | null
  icon_url?: string | null
  logo_url?: string | null
  session_place?: string | null
  speakers?: Array<{ id: string, name: string | null, profile?: any }>
}

export interface ReceptionAd {
  id: string | number
  title: string
  placement: string
  images: Array<{ url?: string, redirect_url?: string, [k: string]: any }>
}

export interface ReceptionPayload {
  about: {
    name: string
    description: string | null
    format: string | null
    starts_at: string | null
    ends_at: string | null
    timezone: string | null
    location: any
    logo_url: string | null
    cover_url: string | null
    social: Record<string, string>
  }
  event: { uuid: string, name: string, slug: string }
  banners: string[]
  ads: { strip: ReceptionAd[], sidebar: ReceptionAd[] }
  sessions: ReceptionSession[]
  speakers: ReceptionSpeaker[]
  exhibitors: ReceptionPartner[]
  sponsors: ReceptionPartner[]
}

/**
 * The public "Reception" (post-login attendee home) payload for the event this
 * subdomain resolves to. Mirrors stores/site.ts — a single public GET, scoped
 * to the subdomain. Per-user data (the visitor's own meetings) is loaded
 * separately by the page from the authed participant endpoint.
 */
export const useReceptionStore = defineStore('reception', {
  state: () => ({
    data: null as ReceptionPayload | null,
    loading: false,
    loaded: false,
    error: false,
  }),

  actions: {
    async fetchReception() {
      const sub = useEventSubdomain()
      if (!sub) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: ReceptionPayload }>(`${apiBase}/public/reception`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.data = res.data
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },
  },
})

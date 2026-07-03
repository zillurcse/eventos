import { defineStore } from 'pinia'

interface SiteEvent {
  uuid: string
  name: string
  slug: string
  description?: string | null
  format?: string | null
  starts_at?: string | null
  ends_at?: string | null
  timezone?: string | null
  location?: any
  cover_url?: string | null
}

interface SiteBranding {
  logo_url: string | null
  primary: string
  accent: string
  banners: string[]
  login: { type: string, banner_url: string | null, video_url: string | null, website_url: string | null }
}

interface SitePayload {
  event: SiteEvent
  branding: SiteBranding
  login: { methods: string[], require_login: boolean }
  seo: { meta_title: string | null, meta_description: string | null, favicon_url: string | null }
  subdomain: string
  registration_form_uuid: string | null
  powered_by: string
}

/**
 * The public config for the event this subdomain resolves to. Loaded once at
 * boot (see plugins/site.client.ts) so the branded landing/login page can
 * render before anyone signs in. Holds no auth state — that's the auth store.
 */
export const useSiteStore = defineStore('site', {
  state: () => ({
    subdomain: null as string | null,
    site: null as SitePayload | null,
    loading: false,
    notFound: false,
  }),

  getters: {
    event: (s): SiteEvent | null => s.site?.event ?? null,
    branding: (s): SiteBranding | null => s.site?.branding ?? null,
    name: (s): string => s.site?.event?.name ?? 'Event',
    logoUrl: (s): string | null => s.site?.branding?.logo_url ?? null,
    poweredBy: (s): string => s.site?.powered_by ?? 'EXPOUSE',
    registrationFormUuid: (s): string | null => s.site?.registration_form_uuid ?? null,
  },

  actions: {
    async fetchSite() {
      const sub = useEventSubdomain()
      this.subdomain = sub

      if (!sub) {
        this.notFound = true
        return
      }

      this.loading = true
      this.notFound = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: SitePayload }>(`${apiBase}/public/site`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.site = res.data
        this.applyBranding()
      } catch {
        this.notFound = true
      } finally {
        this.loading = false
      }
    },

    /** Paint the event's theme colours + title/favicon so each event self-brands. */
    applyBranding() {
      if (!import.meta.client || !this.site) return

      const root = document.documentElement
      root.style.setProperty('--brand-primary', this.site.branding.primary)
      root.style.setProperty('--brand-accent', this.site.branding.accent)

      document.title = this.site.seo.meta_title || this.site.event.name

      const href = this.site.seo.favicon_url
      if (href) {
        let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
        if (!link) {
          link = document.createElement('link')
          link.rel = 'icon'
          document.head.appendChild(link)
        }
        link.href = href
      }
    },
  },
})

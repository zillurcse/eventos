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

/** The tab bar the organizer built in admin › Navigation & Menu › Web App Tabs.
 *  Only the enabled tabs arrive, already in their order. Empty when the
 *  organizer never touched that screen — the header then uses its defaults. */
export interface SiteNavigation {
  tabs: { key: string, label: string }[]
  icons: boolean
  background: boolean
  alignment: string
  /** The "Filter By" rail on the feed (admin › Allowed Feed Tabs). */
  feed_tabs: { key: string, label: string }[]
  /** Header brand block + quick actions (admin › Modules). All default to on. */
  modules: Record<string, boolean>
  /** Null when there is no video, or the organizer switched off both triggers. */
  welcome_video: WelcomeVideo | null
}

export interface WelcomeVideo {
  type: 'youtube' | 'vimeo' | 'uploaded' | string
  url: string
  /** Null when the pasted link couldn't be parsed — show nothing, not an empty player. */
  embed_url: string | null
  show_after_login: boolean
  show_on_home: boolean
}

/** A field of the published attendee profile form (admin › Event Settings ›
 *  Profile). `meta.surfaces` says where it's collected — signup renders the
 *  `registration` surface, the onboarding modal the `onboarding` surface. */
export interface ProfileFormField {
  key: string
  label: string | null
  help_text: string | null
  type: string
  required: boolean
  meta: {
    placeholder?: string
    width?: number
    surfaces?: { registration?: boolean, onboarding?: boolean, public?: boolean }
    [k: string]: any
  } | null
  options: { label: string, value: string | null }[]
}

interface SitePayload {
  event: SiteEvent
  branding: SiteBranding
  login: {
    /** Which sign-in doors are open — social keys already exclude providers with
     *  no OAuth app, so a `true` is a channel the login page can render. */
    channels: Record<string, boolean>
    require_login: boolean
    onboarding: boolean
    methods: string[]
  }
  seo: { meta_title: string | null, meta_description: string | null, favicon_url: string | null }
  navigation: SiteNavigation
  subdomain: string
  registration_form_uuid: string | null
  profile_form: { uuid: string, fields: ProfileFormField[] } | null
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
    navigation: (s): SiteNavigation | null => s.site?.navigation ?? null,
    welcomeVideo: (s): WelcomeVideo | null => s.site?.navigation?.welcome_video ?? null,
    poweredBy: (s): string => s.site?.powered_by ?? 'EXPOUSE',
    registrationFormUuid: (s): string | null => s.site?.registration_form_uuid ?? null,
    /** Registration-surface fields of the attendee profile form — what the
     *  inline signup step collects. An email field is always kept: signup
     *  creates a login, which cannot exist without an address, whatever the
     *  organizer toggled. */
    registrationFields(): ProfileFormField[] {
      return (this.site?.profile_form?.fields ?? []).filter(f =>
        !['section_break', 'recaptcha', 'file'].includes(f.type)
        && ((f.meta?.surfaces?.registration ?? true) !== false || f.type === 'email'),
      )
    },
    /** Onboarding-surface fields of the attendee profile form, minus the
     *  basics the onboarding page already asks for natively. */
    onboardingFields(): ProfileFormField[] {
      const NATIVE = ['first_name', 'last_name', 'email', 'job_title', 'company', 'bio', 'interests']
      return (this.site?.profile_form?.fields ?? []).filter(f =>
        !NATIVE.includes(f.key)
        && !['section_break', 'recaptcha', 'file'].includes(f.type)
        && (f.meta?.surfaces?.onboarding ?? true) !== false,
      )
    },
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

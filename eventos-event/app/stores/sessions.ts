import { defineStore } from 'pinia'

export interface SessionSpeaker {
  id: string
  name: string | null
  role?: string | null
  profile?: { image_url?: string | null, designation?: string, company?: string, [k: string]: any } | null
}

export interface SessionSponsor {
  id: string
  name: string
  logo_url?: string | null
}

export interface SessionTrack {
  id: number
  name: string
  color?: string | null
}

export interface SessionDocument {
  name: string
  url: string
}

export interface AgendaSession {
  id: string
  title: string
  description: string | null
  starts_at: string | null
  ends_at: string | null
  timezone: string | null
  status: string | null
  capacity: number | null
  stream_url: string | null
  session_place: string | null
  logo_url: string | null
  icon_url: string | null
  tags: string[]
  sponsors: SessionSponsor[]
  documents: SessionDocument[]
  is_featured: boolean
  is_stream: boolean
  stream_link: string | null
  on_demand_recording_link: string | null
  who_will_host: string | null
  vimeo_live_id: string | null
  can_live_chat: boolean
  can_qa: boolean
  can_live_polls: boolean
  can_attendee_list: boolean
  can_session: boolean
  track: SessionTrack | null
  speakers: SessionSpeaker[]
}

export interface SessionsPayload {
  event: { uuid: string, name: string, timezone: string, starts_at: string | null, ends_at: string | null }
  tracks: SessionTrack[]
  tags: string[]
  speakers: Array<{ id: string, name: string | null, image_url: string | null }>
  sessions: AgendaSession[]
}

export interface SessionsAd {
  id: string | number
  title: string
  placement: string
  images: Array<{ image_url?: string, url?: string, redirect_url?: string, is_active?: boolean, [k: string]: any }>
}

/**
 * The public "Sessions" (agenda) payload for the event this subdomain resolves
 * to. Mirrors stores/reception.ts — a single public GET scoped to the subdomain.
 * Day/track/tag/speaker filtering is all client-side over this list.
 */
export const useSessionsStore = defineStore('sessions', {
  state: () => ({
    data: null as SessionsPayload | null,
    loading: false,
    loaded: false,
    error: false,
    // The subdomain `data` was fetched for. A stale store from a previously
    // viewed event must not be mistaken for "loaded" once the tab switches
    // events (deep link, ?subdomain= change) — that silently searches the
    // wrong event's session list and every lookup by id comes up empty.
    loadedSubdomain: null as string | null,
    ads: [] as SessionsAd[],
    adsLoaded: false,
  }),

  getters: {
    sessions: (s): AgendaSession[] => s.data?.sessions ?? [],
    tracks: (s): SessionTrack[] => s.data?.tracks ?? [],
    tags: (s): string[] => s.data?.tags ?? [],
    speakerOptions: (s) => s.data?.speakers ?? [],
    eventTimezone: (s): string => s.data?.event.timezone || 'UTC',
    eventUuid: (s): string | null => s.data?.event.uuid ?? null,
  },

  actions: {
    /** Safe to call unconditionally — no-ops once `data` is fresh for the
     *  current subdomain, refetches when it isn't. */
    async fetchSessions() {
      const sub = useEventSubdomain()
      if (!sub) { this.error = true; return }
      if (this.loading) return
      if (this.loaded && this.loadedSubdomain === sub) return

      this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: SessionsPayload }>(`${apiBase}/public/sessions`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.data = res.data
        this.loaded = true
        this.loadedSubdomain = sub
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    /** The "main ads" strip (organizer's AD Managements, targeted to the
     *  Sessions page) — shown as a banner above the day tabs. */
    async fetchAds() {
      if (this.adsLoaded) return
      const sub = useEventSubdomain()
      if (!sub) return
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: { strip: SessionsAd[], sidebar: SessionsAd[] } }>(`${apiBase}/public/ads`, {
          query: { page: 'sessions' },
          headers: { 'X-Event-Subdomain': sub },
        })
        this.ads = res.data.strip
      } catch {
        // Ads are decorative — fail silently, the page still works without them.
      } finally {
        this.adsLoaded = true
      }
    },
  },
})

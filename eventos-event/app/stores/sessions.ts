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
  is_featured: boolean
  is_stream: boolean
  stream_link: string | null
  on_demand_recording_link: string | null
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
  }),

  getters: {
    sessions: (s): AgendaSession[] => s.data?.sessions ?? [],
    tracks: (s): SessionTrack[] => s.data?.tracks ?? [],
    tags: (s): string[] => s.data?.tags ?? [],
    speakerOptions: (s) => s.data?.speakers ?? [],
    eventTimezone: (s): string => s.data?.event.timezone || 'UTC',
  },

  actions: {
    async fetchSessions() {
      const sub = useEventSubdomain()
      if (!sub) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: SessionsPayload }>(`${apiBase}/public/sessions`, {
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

import { defineStore } from 'pinia'

export interface Speaker {
  id: string
  name: string | null
  designation: string
  company: string
  category: string
  bio: string
  image_url: string | null
  is_featured: boolean
  social: Record<string, string>
}

export interface SpeakerCategory { id: string, name: string }

interface SpeakersPayload {
  speakers: Speaker[]
  categories: SpeakerCategory[]
}

/**
 * The public speaker directory ("Speakers" tab) for the event this subdomain
 * resolves to. Mirrors stores/sessions.ts — a single public GET scoped to the
 * subdomain; sort/search/category filtering is client-side over the full list.
 * Sending a connection request is a separate authed call (see connect()).
 */
export const useSpeakersStore = defineStore('speakers', {
  state: () => ({
    speakers: [] as Speaker[],
    categories: [] as SpeakerCategory[],
    loading: false,
    loaded: false,
    error: false,
    // Local connection state keyed by speaker id (optimistic, this session).
    connected: {} as Record<string, 'pending' | 'error'>,
  }),

  actions: {
    async fetchSpeakers() {
      const sub = useEventSubdomain()
      if (!sub) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: SpeakersPayload }>(`${apiBase}/public/speakers`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.speakers = res.data.speakers
        this.categories = res.data.categories
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    /** Send a connection request to a speaker (requires being signed in). */
    async connect(speaker: Speaker) {
      const uuid = useSiteStore().event?.uuid
      const auth = useAuthStore()
      if (!uuid || !auth.isAuthed) return
      this.connected[speaker.id] = 'pending'
      try {
        const api = useApi()
        await api(`/events/${uuid}/connections`, { method: 'POST', body: { to: speaker.id } })
      } catch {
        this.connected[speaker.id] = 'error'
      }
    },
  },
})

import { defineStore } from 'pinia'

export interface BreakoutRoom {
  id: number
  uuid: string
  name: string
  description: string | null
  purpose: string
  type: string
  access_type: 'anyone' | 'coded' | 'hidden'
  has_access_code: boolean
  capacity: number | null
  poster_url: string | null
  provider: string
  meeting_url: string | null
  starts_at: string | null
  ends_at: string | null
}

/** LiveKit join config returned by the attendee token endpoint. */
export interface JoinConfig {
  provider: string
  url: string
  room: string
  token: string
}

/**
 * The attendee-facing breakout rooms ("Rooms" tab) for the event this subdomain
 * resolves to. Listing is a public read (mirrors stores/reception.ts); joining a
 * room mints a per-user media token from the authed participant endpoint.
 */
export const useRoomsStore = defineStore('rooms', {
  state: () => ({
    rooms: [] as BreakoutRoom[],
    loading: false,
    loaded: false,
    error: false,
  }),

  actions: {
    async fetchRooms() {
      const sub = useEventSubdomain()
      if (!sub) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: BreakoutRoom[] }>(`${apiBase}/public/rooms`, {
          headers: { 'X-Event-Subdomain': sub },
        })
        this.rooms = res.data
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },
  },
})

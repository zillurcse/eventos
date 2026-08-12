import { defineStore } from 'pinia'
import type { ReceptionAd } from '~/stores/reception'

export interface RoomOccupant {
  identity: string
  name: string
  avatar_url: string | null
  seat: number | null
}

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
  occupied: number
  occupants: RoomOccupant[]
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

    /** Main ads strip targeted at the Rooms page. */
    ads: [] as ReceptionAd[],
    adsLoaded: false,
  }),

  actions: {
    async fetchRooms(silent = false) {
      const identity = useEventIdentity()
      const id = identity.subdomain || identity.host
      if (!id) { this.error = true; return }

      if (!silent) this.loading = true
      this.error = false
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: BreakoutRoom[] }>(`${apiBase}/public/rooms`, {
          headers: eventIdentityHeaders(),
        })
        this.rooms = (res.data ?? []).map((r) => ({
          ...r,
          occupied: r.occupied ?? 0,
          occupants: r.occupants ?? [],
        }))
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        if (!silent) this.loading = false
      }
    },

    /** The organizer's "main ads" strip targeted at the Rooms page. */
    async fetchAds() {
      if (this.adsLoaded) return
      try {
        this.ads = (await fetchPageAds('rooms')).strip
      } finally {
        this.adsLoaded = true
      }
    },
  },
})

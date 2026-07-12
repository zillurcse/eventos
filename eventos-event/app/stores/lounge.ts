import { defineStore } from 'pinia'

export interface LoungeBusy { date: string, slot: string }

export interface LoungeAvailability {
  enabled: boolean
  slots_open_all: boolean
  timezone: string
  dates: string[]
  slots: Record<string, string[]>
  busy: LoungeBusy[]
  format: string                // venue | online | hybrid
  location_required: boolean    // true on a venue/hybrid event
  locations: string[]           // places the organizer allows, e.g. ["Hall 4"]
}

/**
 * Networking-lounge slot availability for the meeting booking picker. The
 * organizer configures bookable slots per day (Admin → Communication → Lounge);
 * this fetches those slots for the current event plus the slots already taken by
 * me and the person I'm about to invite (?with=<uuid>) so the picker only offers
 * free ones. Fetched per counterpart, cached by their uuid for the session.
 */
export const useLoungeStore = defineStore('lounge', {
  state: () => ({
    byCounterpart: {} as Record<string, LoungeAvailability>,
    loading: false,
    loaded: false,
    error: false,
  }),

  actions: {
    /** Fetch availability for booking with a specific delegate. */
    async fetchFor(counterpartId: string): Promise<LoungeAvailability | null> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) { this.error = true; return null }

      this.loading = true
      this.error = false
      try {
        const api = useApi()
        const res = await api<{ data: LoungeAvailability }>(
          `/events/${uuid}/lounge?with=${encodeURIComponent(counterpartId)}`,
        )
        this.byCounterpart[counterpartId] = res.data
        this.loaded = true
        return res.data
      } catch {
        this.error = true
        return null
      } finally {
        this.loading = false
      }
    },
  },
})

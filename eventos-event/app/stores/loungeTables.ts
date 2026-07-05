import { defineStore } from 'pinia'
import type { JoinConfig } from '~/stores/rooms'

export type LoungeTableKind = 'attendee' | 'exhibitor' | 'sponsor'

export interface LoungeOccupant {
  identity: string
  name: string
  avatar_url: string | null
}

export interface LoungeTable {
  id: string
  kind: LoungeTableKind
  name: string
  capacity: number
  image_url: string | null
  occupants: LoungeOccupant[]
  occupied: number
  live: boolean
  full: boolean
}

export interface LoungeTabs {
  attendees: LoungeTable[]
  exhibitors: LoungeTable[]
  sponsors: LoungeTable[]
}

/**
 * Networking-lounge tables ("Lounge" tab). The organizer configures attendee
 * tables (Admin → Communication → Lounge) and the event's exhibitors/sponsors
 * each get a branded table; every table is a live LiveKit video room. Listing +
 * occupancy come from the authed participant endpoint; "Join us" mints a media
 * token opened in the shared RoomStage. Occupancy is refreshed on a poll while
 * the page is open so seats + the green "live" dot stay current.
 */
export const useLoungeTablesStore = defineStore('loungeTables', {
  state: () => ({
    tabs: { attendees: [], exhibitors: [], sponsors: [] } as LoungeTabs,
    enabled: false,
    loading: false,
    loaded: false,
    error: false,
    joining: '' as string,
    joinError: '' as string,
  }),

  actions: {
    async fetchTables(silent = false) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) { this.error = true; return }

      if (!silent) this.loading = true
      this.error = false
      try {
        const api = useApi()
        const res = await api<{ data: { enabled: boolean, tabs: LoungeTabs } }>(`/events/${uuid}/lounge/tables`)
        this.tabs = res.data.tabs
        this.enabled = res.data.enabled
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    /** Take a seat at a table. Returns the video join config (+ title) or null. */
    async join(table: LoungeTable): Promise<(JoinConfig & { title: string }) | null> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return null

      this.joining = table.id
      this.joinError = ''
      try {
        const api = useApi()
        const avatar = (useAuthStore().user as any)?.avatar_url ?? null
        const res = await api<{ data: JoinConfig & { title: string } }>(
          `/events/${uuid}/lounge/tables/${encodeURIComponent(table.id)}/join`,
          { method: 'POST', body: { avatar_url: avatar } },
        )
        return res.data
      } catch (e: any) {
        this.joinError = e?.data?.errors?.table?.[0] || e?.data?.message || 'Could not join this table.'
        return null
      } finally {
        this.joining = ''
      }
    },
  },
})

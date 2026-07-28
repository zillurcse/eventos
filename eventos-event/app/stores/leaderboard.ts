import { defineStore } from 'pinia'

export interface LeaderboardEntry {
  rank: number
  name: string
  role?: string | null
  avatar_url?: string | null
  points: number
  is_me: boolean
}

export interface LeaderboardPayload {
  enabled: boolean
  award_title?: string | null
  award_description?: string | null
  my_points: number
  leaderboard: LeaderboardEntry[]
}

/**
 * Attendee gamification leaderboard — opened from the topbar trophy icon.
 * Fetches GET /events/{uuid}/my/gamification (top 20 + caller's points).
 */
export const useLeaderboardStore = defineStore('leaderboard', {
  state: () => ({
    enabled: true,
    awardTitle: null as string | null,
    awardDescription: null as string | null,
    myPoints: 0,
    entries: [] as LeaderboardEntry[],
    loaded: false,
    loading: false,
    drawerOpen: false,
  }),

  getters: {
    topThree: (s): LeaderboardEntry[] => s.entries.slice(0, 3),
    rest: (s): LeaderboardEntry[] => s.entries.slice(3),
  },

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    toggleDrawer() {
      this.drawerOpen = !this.drawerOpen
      if (this.drawerOpen) this.fetch()
    },
    closeDrawer() { this.drawerOpen = false },

    async fetch() {
      const uuid = this.eventUuid()
      if (!uuid || !useAuthStore().isAuthed) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: LeaderboardPayload }>(`/events/${uuid}/my/gamification`)
        const d = res.data
        this.enabled = !!d.enabled
        this.awardTitle = d.award_title ?? null
        this.awardDescription = d.award_description ?? null
        this.myPoints = d.my_points ?? 0
        this.entries = d.leaderboard ?? []
        this.loaded = true
      } catch {
        // stay unloaded; retried on next open
      } finally {
        this.loading = false
      }
    },
  },
})

import { defineStore } from 'pinia'

export type BookmarkType = 'speaker' | 'session' | 'delegate' | 'exhibitor'

const TYPES: BookmarkType[] = ['speaker', 'session', 'delegate', 'exhibitor']

/**
 * Per-attendee bookmarks shared by the Speakers / Sessions / Delegates /
 * Exhibitors tabs. Persisted server-side on the caller's participation
 * (GET/POST /events/{uuid}/bookmarks) so saves follow the account across
 * devices; toggles are optimistic with rollback on failure. Bookmarks saved
 * by the old localStorage-only implementation (eventos:bookmark:{type}:{id})
 * are migrated up to the account once, on first load.
 */
export const useBookmarksStore = defineStore('bookmarks', {
  state: () => ({
    saved: {
      speaker: {}, session: {}, delegate: {}, exhibitor: {},
    } as Record<BookmarkType, Record<string, boolean>>,
    loading: false,
    loaded: false,
  }),

  getters: {
    isOn: s => (type: BookmarkType, id: string) => !!s.saved[type][id],
    count: s => (type: BookmarkType) => Object.values(s.saved[type]).filter(Boolean).length,
  },

  actions: {
    async fetch() {
      if (this.loaded || this.loading) return
      const uuid = useSiteStore().event?.uuid
      if (!uuid || !useAuthStore().isAuthed) return

      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: Record<BookmarkType, string[]> }>(`/events/${uuid}/bookmarks`)
        for (const t of TYPES) {
          this.saved[t] = Object.fromEntries((res.data[t] ?? []).map(id => [id, true]))
        }
        this.loaded = true
        this.migrateLegacy()
      } catch {
        // Stay unloaded — the next page visit retries; toggles still work.
      } finally {
        this.loading = false
      }
    },

    /** One-time move of pre-server localStorage bookmarks up to the account. */
    migrateLegacy() {
      if (!import.meta.client) return
      const legacy: Array<[BookmarkType, string]> = []
      for (let i = 0; i < localStorage.length; i++) {
        const m = localStorage.key(i)?.match(/^eventos:bookmark:(speaker|session|delegate|exhibitor):(.+)$/)
        if (m) legacy.push([m[1] as BookmarkType, m[2]!])
      }
      for (const [type, id] of legacy) {
        localStorage.removeItem(`eventos:bookmark:${type}:${id}`)
        if (!this.saved[type][id]) this.toggle(type, id)
      }
    },

    async toggle(type: BookmarkType, id: string) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      const on = !this.saved[type][id]
      this.saved[type][id] = on // optimistic
      try {
        const api = useApi()
        await api(`/events/${uuid}/bookmarks`, { method: 'POST', body: { type, id, on } })
      } catch {
        this.saved[type][id] = !on // roll back
      }
    },
  },
})

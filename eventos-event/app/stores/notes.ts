import { defineStore } from 'pinia'

export type NoteType = 'speaker' | 'session' | 'delegate'

export interface NoteItem {
  id: string
  target_id: string
  text: string
  created_at: string
  updated_at: string
}

const TYPES: NoteType[] = ['speaker', 'session', 'delegate']

/**
 * Per-attendee notes jotted against a speaker, session, or delegate from
 * their respective cards (one note per target). Persisted server-side on
 * the caller's participation (GET/POST/DELETE /events/{uuid}/notes) so it
 * follows the account across devices; browsable later from Profile ›
 * My Briefcase › Notes.
 */
export const useNotesStore = defineStore('notes', {
  state: () => ({
    items: { speaker: [], session: [], delegate: [] } as Record<NoteType, NoteItem[]>,
    loading: false,
    loaded: false,
  }),

  getters: {
    noteFor: s => (type: NoteType, targetId: string) => s.items[type].find(n => n.target_id === targetId) ?? null,
    count: s => (type: NoteType) => s.items[type].length,
  },

  actions: {
    async fetch() {
      if (this.loaded || this.loading) return
      const uuid = useSiteStore().event?.uuid
      if (!uuid || !useAuthStore().isAuthed) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: Record<NoteType, NoteItem[]> }>(`/events/${uuid}/notes`)
        for (const t of TYPES) this.items[t] = res.data[t] ?? []
        this.loaded = true
      } catch {
        // stay unloaded; retried on next open
      } finally {
        this.loading = false
      }
    },

    async save(type: NoteType, targetId: string, text: string) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      const api = useApi()
      const res = await api<{ data: NoteItem }>(`/events/${uuid}/notes`, {
        method: 'POST', body: { type, target_id: targetId, text },
      })
      const list = this.items[type]
      const i = list.findIndex(n => n.target_id === targetId)
      if (i >= 0) list[i] = res.data
      else list.push(res.data)
    },

    async remove(type: NoteType, targetId: string) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      const prev = this.items[type]
      this.items[type] = prev.filter(n => n.target_id !== targetId)
      try {
        const api = useApi()
        await api(`/events/${uuid}/notes/${type}/${targetId}`, { method: 'DELETE' })
      } catch {
        this.items[type] = prev
      }
    },
  },
})

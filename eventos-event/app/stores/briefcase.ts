import { defineStore } from 'pinia'

export interface BriefcaseItem {
  id: string
  title: string
  url: string
  kind: string
}

/** Guess a file kind from its URL extension (for the icon + "PDF FILE" label). */
export function briefcaseKind(url: string): string {
  const ext = (url.split('?')[0]?.split('.').pop() || '').toLowerCase()
  if (ext === 'pdf') return 'pdf'
  if (['doc', 'docx'].includes(ext)) return 'doc'
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'excel'
  if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) return 'image'
  return 'file'
}

/**
 * The attendee's personal "Briefcase" — files saved from around the event app
 * (exhibitor brochures, session docs…). Persisted server-side on the caller's
 * participation (GET/POST/DELETE /events/{uuid}/briefcase) so it follows the
 * account across devices. Opened from the topbar briefcase icon.
 */
export const useBriefcaseStore = defineStore('briefcase', {
  state: () => ({
    items: [] as BriefcaseItem[],
    loaded: false,
    loading: false,
    drawerOpen: false,
  }),

  getters: {
    count: s => s.items.length,
    hasUrl: s => (url: string) => s.items.some(i => i.url === url),
    itemByUrl: s => (url: string) => s.items.find(i => i.url === url) ?? null,
  },

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    toggleDrawer() {
      this.drawerOpen = !this.drawerOpen
      if (this.drawerOpen && !this.loaded) this.fetch()
    },
    closeDrawer() { this.drawerOpen = false },

    async fetch() {
      const uuid = this.eventUuid()
      if (!uuid || !useAuthStore().isAuthed) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: BriefcaseItem[] }>(`/events/${uuid}/briefcase`)
        this.items = res.data
        this.loaded = true
      } catch {
        // stay unloaded; retried on next open
      } finally {
        this.loading = false
      }
    },

    async add(doc: { title: string, url: string, kind?: string }) {
      const uuid = this.eventUuid()
      if (!uuid || !doc.url || this.hasUrl(doc.url)) return
      const kind = doc.kind || briefcaseKind(doc.url)
      // optimistic
      const temp: BriefcaseItem = { id: 'tmp-' + Date.now(), title: doc.title, url: doc.url, kind }
      this.items.push(temp)
      try {
        const api = useApi()
        const res = await api<{ data: BriefcaseItem }>(`/events/${uuid}/briefcase`, {
          method: 'POST', body: { title: doc.title, url: doc.url, kind },
        })
        const i = this.items.findIndex(x => x.id === temp.id)
        if (i >= 0) this.items[i] = res.data
      } catch {
        this.items = this.items.filter(x => x.id !== temp.id)
      }
    },

    async remove(id: string) {
      const uuid = this.eventUuid()
      if (!uuid) return
      const prev = this.items
      this.items = this.items.filter(i => i.id !== id)
      try {
        const api = useApi()
        await api(`/events/${uuid}/briefcase/${id}`, { method: 'DELETE' })
      } catch {
        this.items = prev
      }
    },

    /** Add if new, remove if already saved (used by the file's briefcase button). */
    async toggleDoc(doc: { title: string, url: string, kind?: string }) {
      const existing = this.itemByUrl(doc.url)
      if (existing) await this.remove(existing.id)
      else await this.add(doc)
    },
  },
})

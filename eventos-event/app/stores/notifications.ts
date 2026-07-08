import { defineStore } from 'pinia'

export interface AppNotification {
  id: string
  title: string
  body: string | null
  status: string
  read_at: string | null
  created_at: string | null
}

/**
 * The topbar notification center. Notifications live on the identity plane
 * (`/notifications`, cross-event) — announcements, connection accepts, meeting
 * responses, etc. Kept fresh with a light poll while the app is open plus a
 * refetch on window focus; a bell badge shows the unread count.
 */
export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [] as AppNotification[],
    unread: 0,
    loaded: false,
    loading: false,
    _timer: null as ReturnType<typeof setInterval> | null,
    _onFocus: null as (() => void) | null,
  }),

  actions: {
    async fetch() {
      if (this.loading) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ unread: number, data: AppNotification[] }>('/notifications')
        this.items = res.data
        this.unread = res.unread
        this.loaded = true
      } catch {
        // keep the last known state; the next poll retries
      } finally {
        this.loading = false
      }
    },

    async markRead(n: AppNotification) {
      if (n.read_at) return
      n.read_at = new Date().toISOString()
      this.unread = Math.max(0, this.unread - 1)
      const api = useApi()
      await api(`/notifications/${n.id}/read`, { method: 'PATCH' }).catch(() => {})
    },

    async readAll() {
      if (!this.unread) return
      const now = new Date().toISOString()
      this.items.forEach((n) => { n.read_at = n.read_at ?? now })
      this.unread = 0
      const api = useApi()
      await api('/notifications/read-all', { method: 'POST' }).catch(() => {})
    },

    /** Poll every 45s + refresh when the tab regains focus. Idempotent. */
    start() {
      if (this._timer) return
      this.fetch()
      this._timer = setInterval(() => this.fetch(), 45_000)
      this._onFocus = () => this.fetch()
      window.addEventListener('focus', this._onFocus)
    },

    stop() {
      if (this._timer) clearInterval(this._timer)
      if (this._onFocus) window.removeEventListener('focus', this._onFocus)
      this._timer = null
      this._onFocus = null
    },
  },
})

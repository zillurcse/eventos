import { defineStore } from 'pinia'

/**
 * Online-presence heartbeat. While a signed-in tab is open we POST
 * /events/{uuid}/presence every 60s; the API keeps a short-TTL Redis key per
 * participation, and directory endpoints (e.g. delegates) read it to mark
 * people online/offline. Started/stopped by the event topbar, same lifecycle
 * as the notifications poller.
 */
export const usePresenceStore = defineStore('presence', {
  state: () => ({
    timer: null as ReturnType<typeof setInterval> | null,
  }),

  actions: {
    start() {
      if (this.timer || !import.meta.client) return
      this.ping()
      this.timer = setInterval(() => this.ping(), 60_000)
    },

    stop() {
      if (this.timer) {
        clearInterval(this.timer)
        this.timer = null
      }
    },

    async ping() {
      const uuid = useSiteStore().event?.uuid
      if (!uuid || !useAuthStore().isAuthed) return
      try {
        const api = useApi()
        await api(`/events/${uuid}/presence`, { method: 'POST' })
      } catch {
        // Best-effort — a missed beat just shows us offline for a bit.
      }
    },
  },
})

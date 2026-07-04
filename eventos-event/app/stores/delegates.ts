import { defineStore } from 'pinia'

export interface Delegate {
  id: string
  name: string | null
  company: string
  job_title: string
  avatar_url: string | null
}

/**
 * The delegate (attendee) directory ("Delegates" tab). Authenticated + scoped
 * to the event via useApi() → `/events/{uuid}/delegates`. Sort/search filtering
 * is client-side over the full list. Sending a connection request reuses the
 * networking connections endpoint (see connect()).
 */
export const useDelegatesStore = defineStore('delegates', {
  state: () => ({
    delegates: [] as Delegate[],
    loading: false,
    loaded: false,
    error: false,
    connected: {} as Record<string, 'pending' | 'error'>,
  }),

  actions: {
    async fetchDelegates() {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const api = useApi()
        const res = await api<{ data: Delegate[] }>(`/events/${uuid}/delegates`)
        this.delegates = res.data
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    /** Send a connection request to a delegate. */
    async connect(delegate: Delegate) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return
      this.connected[delegate.id] = 'pending'
      try {
        const api = useApi()
        await api(`/events/${uuid}/connections`, { method: 'POST', body: { to: delegate.id } })
      } catch {
        this.connected[delegate.id] = 'error'
      }
    },
  },
})

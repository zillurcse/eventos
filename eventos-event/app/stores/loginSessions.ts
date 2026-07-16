import { defineStore } from 'pinia'

export interface LoginSession {
  id: number
  device: string
  ip_address: string | null
  last_active_at: string | null
  is_current: boolean
}

/**
 * The signed-in user's active login sessions (Profile › Account Settings ›
 * Browser Session) — one row per Sanctum token, not a browser cookie session,
 * since this app is API-token auth end to end. Same list backs "Logout" on a
 * single row and "Logout from all sessions" for the rest. Named apart from
 * stores/sessions.ts, which is the unrelated public agenda ("Sessions" tab).
 */
export const useLoginSessionsStore = defineStore('loginSessions', {
  state: () => ({
    sessions: [] as LoginSession[],
    loading: false,
    loaded: false,
  }),

  actions: {
    async fetch(force = false) {
      if ((this.loaded && !force) || this.loading) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: LoginSession[] }>('/auth/sessions')
        this.sessions = res.data
        this.loaded = true
      } finally {
        this.loading = false
      }
    },

    async revoke(id: number) {
      const api = useApi()
      await api(`/auth/sessions/${id}`, { method: 'DELETE' })
      this.sessions = this.sessions.filter(s => s.id !== id)
    },

    async logoutOthers() {
      const api = useApi()
      await api('/auth/sessions/logout-others', { method: 'POST' })
      this.sessions = this.sessions.filter(s => s.is_current)
    },
  },
})

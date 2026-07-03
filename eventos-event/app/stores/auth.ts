import { defineStore } from 'pinia'

interface User {
  id: string
  name: string
  email: string
  memberships?: Array<{ organization: { name: string }, roles?: string[] }>
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null as string | null,
    user: null as User | null,
  }),

  getters: {
    isAuthed: (s): boolean => !!s.token,
    orgName: (s): string | null => s.user?.memberships?.[0]?.organization?.name ?? null,
  },

  actions: {
    init() {
      if (import.meta.client && !this.token) {
        this.token = localStorage.getItem('eventos_token')
      }
    },

    /** Does this email already have a login? Drives the single-field flow:
     *  has_password → password step (login); otherwise → registration form. */
    async checkEmail(email: string): Promise<{ exists: boolean, has_password: boolean }> {
      const { public: { apiBase } } = useRuntimeConfig()
      return $fetch(`${apiBase}/public/check-email`, {
        method: 'POST',
        headers: this.subHeaders(),
        body: { email },
      })
    },

    async login(email: string, password: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      const res = await $fetch<{ token: string, user: User }>(`${apiBase}/auth/login`, {
        method: 'POST',
        headers: this.subHeaders(),
        body: { email, password },
      })
      this.token = res.token
      this.user = res.user
      if (import.meta.client) localStorage.setItem('eventos_token', res.token)
    },

    /** Attach the resolved event subdomain so auth calls carry event context. */
    subHeaders(): Record<string, string> {
      const sub = useEventSubdomain()
      return sub ? { 'X-Event-Subdomain': sub } : {}
    },

    async fetchMe() {
      if (!this.token) return
      const { public: { apiBase } } = useRuntimeConfig()
      try {
        const res = await $fetch<{ user: User }>(`${apiBase}/auth/me`, {
          headers: { Authorization: `Bearer ${this.token}` },
        })
        this.user = res.user
      } catch {
        this.logout()
      }
    },

    logout() {
      this.token = null
      this.user = null
      if (import.meta.client) localStorage.removeItem('eventos_token')
      navigateTo('/')
    },
  },
})

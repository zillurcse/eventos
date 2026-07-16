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
      this.setSession(res.token, res.user)
    },

    /** Email a one-time sign-in code (Settings › Access authentication › OTP). */
    async requestOtp(email: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      return $fetch<{ sent: boolean, expires_in: number }>(`${apiBase}/public/auth/otp`, {
        method: 'POST',
        headers: this.subHeaders(),
        body: { email },
      })
    },

    /** Trade a valid code for a session. */
    async verifyOtp(email: string, code: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      const res = await $fetch<{ token: string, user: User }>(`${apiBase}/public/auth/otp/verify`, {
        method: 'POST',
        headers: this.subHeaders(),
        body: { email, code },
      })
      this.setSession(res.token, res.user)
    },

    /** Adopt a session — used by the OTP/password/register flows, and by the
     *  social callback which hands the token back in the URL fragment. */
    setSession(token: string, user: User | null) {
      this.token = token
      this.user = user
      if (import.meta.client) localStorage.setItem('eventos_token', token)
    },

    /** A token arrived in the URL (social sign-in). Adopt it, then fetch who we
     *  are — the callback only carries the token, not the profile. */
    async adoptToken(token: string) {
      this.setSession(token, null)
      await this.fetchMe()
    },

    /** Attach the resolved event subdomain so auth calls carry event context. */
    subHeaders(): Record<string, string> {
      const sub = useEventSubdomain()
      return sub ? { 'X-Event-Subdomain': sub } : {}
    },

    /** Profile › Account Settings › Change Password. */
    async changePassword(currentPassword: string, password: string) {
      const api = useApi()
      return api<{ message: string }>('/auth/change-password', {
        method: 'POST',
        body: { current_password: currentPassword, password, password_confirmation: password },
      })
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
      if (import.meta.client) {
        localStorage.removeItem('eventos_token')
        // The welcome video is shown once per login, so signing out has to clear
        // the "seen" flags — otherwise "show after login" would mean "show after
        // the first login this browser ever made".
        for (const key of Object.keys(localStorage)) {
          if (key.startsWith('eventos:welcome_seen:')) localStorage.removeItem(key)
        }
      }
      navigateTo('/')
    },
  },
})

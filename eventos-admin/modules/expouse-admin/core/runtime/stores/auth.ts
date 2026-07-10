import { defineStore } from 'pinia'

export interface ExhibitorLink {
  id: string
  name: string
  type: string            // exhibitor | sponsor
  role: string            // admin | staff
  status: string
  organization?: string
  event?: string
  entitlements?: string[] // enabled Showcase feature keys (empty = all allowed)
}

interface User {
  id: string
  name: string
  email: string
  is_platform_staff?: boolean
  personas?: string[]     // platform | organizer | exhibitor
  memberships?: Array<{ organization: { id?: string, name: string, slug?: string }, status?: string, roles?: string[] }>
  exhibitors?: ExhibitorLink[]
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: null as string | null,
    user: null as User | null,
  }),

  getters: {
    isAuthed: (s): boolean => !!s.token,
    personas: (s): string[] => s.user?.personas ?? [],
    isPlatform: (s): boolean => !!s.user?.is_platform_staff || (s.user?.personas?.includes('platform') ?? false),
    isOrganizer: (s): boolean => s.user?.personas?.includes('organizer') ?? false,
    isExhibitor: (s): boolean => s.user?.personas?.includes('exhibitor') ?? false,
    orgName: (s): string | null => s.user?.memberships?.find(m => m.status === 'active')?.organization?.name ?? null,
    primaryExhibitor: (s): ExhibitorLink | null => s.user?.exhibitors?.[0] ?? null,

    /**
     * Whether the active exhibitor may use a Showcase feature. An empty
     * entitlements list means "never configured" → allow everything, so we
     * never lock an exhibitor out of a booth that was never set up.
     */
    hasFeature: (s) => (key: string): boolean => {
      const list = s.user?.exhibitors?.[0]?.entitlements ?? []
      return list.length === 0 || list.includes(key)
    },

    /** Where this user should land after signing in. */
    home(): string {
      if (this.isPlatform) return '/'
      if (this.isOrganizer) return '/org'
      if (this.isExhibitor) return '/exhibitor'
      return '/login'
    },
  },

  actions: {
    init() {
      if (import.meta.client && !this.token) {
        this.token = localStorage.getItem('eventos_admin_token')
      }
    },

    async login(email: string, password: string) {
      const { public: { apiBase } } = useRuntimeConfig()
      const res = await $fetch<{ token: string, user: User }>(`${apiBase}/auth/login`, {
        method: 'POST',
        body: { email, password },
      })
      this.token = res.token
      this.user = res.user
      if (import.meta.client) localStorage.setItem('eventos_admin_token', res.token)
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
      if (import.meta.client) localStorage.removeItem('eventos_admin_token')
      navigateTo('/login')
    },
  },
})

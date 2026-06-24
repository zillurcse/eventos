import { defineStore } from 'pinia'

export interface PartnerLink {
  id: string
  name: string
  type: string            // exhibitor | sponsor
  role: string            // admin | staff
  status: string
  organization?: string
  event?: string
}

interface User {
  id: string
  name: string
  email: string
  is_platform_staff?: boolean
  personas?: string[]     // platform | organizer | partner
  memberships?: Array<{ organization: { id?: string, name: string, slug?: string }, status?: string, roles?: string[] }>
  partners?: PartnerLink[]
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
    isPartner: (s): boolean => s.user?.personas?.includes('partner') ?? false,
    orgName: (s): string | null => s.user?.memberships?.find(m => m.status === 'active')?.organization?.name ?? null,
    primaryPartner: (s): PartnerLink | null => s.user?.partners?.[0] ?? null,

    /** Where this user should land after signing in. */
    home(): string {
      if (this.isPlatform) return '/'
      if (this.isOrganizer) return '/org'
      if (this.isPartner) return '/partner'
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

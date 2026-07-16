import { defineStore } from 'pinia'

export interface NotificationPref {
  category: string
  email: boolean
  in_app: boolean
}

/**
 * The signed-in user's notification preferences (Profile › Account Settings ›
 * Notifications). Global across every org/event — same GET/PUT
 * /notification-preferences the platform-wide notification system already
 * exposes, not scoped to the current event. `byCategory` gives the settings
 * form O(1) lookups per checkbox without re-deriving on every render.
 */
export const useNotificationPreferencesStore = defineStore('notificationPreferences', {
  state: () => ({
    prefs: [] as NotificationPref[],
    loading: false,
    loaded: false,
    saving: false,
  }),

  getters: {
    byCategory: (state) => (category: string) => state.prefs.find(p => p.category === category),
  },

  actions: {
    async fetch(force = false) {
      if ((this.loaded && !force) || this.loading) return
      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: NotificationPref[] }>('/notification-preferences')
        this.prefs = res.data
        this.loaded = true
      } finally {
        this.loading = false
      }
    },

    /** Bulk save every toggle on the settings page in one request. */
    async save(prefs: NotificationPref[]) {
      this.saving = true
      try {
        const api = useApi()
        const res = await api<{ data: NotificationPref[] }>('/notification-preferences', {
          method: 'PUT',
          body: { prefs },
        })
        this.prefs = res.data
      } finally {
        this.saving = false
      }
    },
  },
})

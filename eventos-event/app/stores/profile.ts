import { defineStore } from 'pinia'

export interface ProfileData {
  name: string | null
  first_name: string
  last_name: string
  email: string | null
  job_title: string
  company: string
  bio: string
  avatar_url: string | null
  phone: string
  gender: string
  country: string
  state: string
  city: string
  zip_code: string
  purpose_of_visit: string
  purchasing_decision: string
  language: string
  timezone: string
  interests: string[]
  looking_for: string[]
  offering: string[]
  social: { linkedin?: string, twitter?: string, website?: string }
  onboarded_at: string | null
}

/**
 * The signed-in attendee's own profile (Edit Profile page). Same
 * GET/PUT /events/{uuid}/profile pair the onboarding modal uses — this store
 * just gives the fuller Edit Profile UI a shared, cached copy to read from and
 * patch, so switching tabs doesn't refetch and a save on one tab doesn't clobber
 * fields owned by another.
 */
export const useProfileStore = defineStore('profile', {
  state: () => ({
    data: null as ProfileData | null,
    loading: false,
    loaded: false,
    saving: false,
  }),

  actions: {
    async fetch(force = false) {
      if ((this.loaded && !force) || this.loading) return
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return

      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: ProfileData }>(`/events/${uuid}/profile`)
        this.data = res.data
        this.loaded = true
      } finally {
        this.loading = false
      }
    },

    /** Merge-save a patch of fields; updates the local copy from the server's
     *  response so every tab stays in sync with what actually persisted. */
    async save(patch: Record<string, unknown>) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) throw new Error('No event context')

      this.saving = true
      try {
        const api = useApi()
        const res = await api<{ data: ProfileData }>(`/events/${uuid}/profile`, {
          method: 'PUT',
          body: patch,
        })
        this.data = res.data
        return res.data
      } finally {
        this.saving = false
      }
    },

    async uploadAvatar(file: File): Promise<string> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) throw new Error('No event context')

      const api = useApi()
      const form = new FormData()
      form.append('file', file)
      form.append('collection', 'avatar')
      const res = await api<{ data: { url: string } }>(`/events/${uuid}/uploads`, {
        method: 'POST',
        body: form,
      })
      await this.save({ avatar_url: res.data.url })
      return res.data.url
    },

    async deleteAvatar() {
      await this.save({ avatar_url: '' })
    },
  },
})

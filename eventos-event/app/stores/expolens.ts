import { defineStore } from 'pinia'

export interface ExpoLensPhoto {
  id: string
  url: string
  caption: string | null
  album: string
  faces_count: number
  created_at: string | null
}

export interface ExpoLensEnrollment {
  consented: boolean
  enrolled: boolean
  status: 'not_enrolled' | 'processing' | 'ready' | 'failed' | string
  /** Why enrollment failed, in wording the attendee can act on. */
  error: string | null
  enrolled_at: string | null
  quality_score: number | null
}

export const useExpoLensStore = defineStore('expolens', {
  state: () => ({
    photos: [] as ExpoLensPhoto[],
    myPhotos: [] as ExpoLensPhoto[],
    enrollment: null as ExpoLensEnrollment | null,
    loadingAll: false,
    loadingMine: false,
    loadingEnrollment: false,
    enrolling: false,
  }),

  actions: {
    eventUuid() {
      return useSiteStore().event?.uuid ?? null
    },

    async fetchAll(force = false) {
      const uuid = this.eventUuid()
      if (!uuid || (this.photos.length && !force) || this.loadingAll) return
      this.loadingAll = true
      try {
        const res = await useApi()<{ data: ExpoLensPhoto[] }>(`/events/${uuid}/expolens/photos`, {
          query: { per_page: 60 },
        })
        this.photos = res.data
      } finally {
        this.loadingAll = false
      }
    },

    async fetchMine(force = false) {
      const uuid = this.eventUuid()
      if (!uuid || (this.myPhotos.length && !force) || this.loadingMine) return
      this.loadingMine = true
      try {
        const res = await useApi()<{
          data: ExpoLensPhoto[]
          meta?: ExpoLensEnrollment
        }>(`/events/${uuid}/expolens/my-photos`, {
          query: { per_page: 60 },
        })
        this.myPhotos = res.data
        if (res.meta) this.enrollment = res.meta
      } finally {
        this.loadingMine = false
      }
    },

    async fetchEnrollment(force = false) {
      const uuid = this.eventUuid()
      if (!uuid || (this.enrollment && !force) || this.loadingEnrollment) return
      this.loadingEnrollment = true
      try {
        const res = await useApi()<{ data: ExpoLensEnrollment }>(`/events/${uuid}/expolens/enrollment`)
        this.enrollment = res.data
      } finally {
        this.loadingEnrollment = false
      }
    },

    async enroll(opts: { fileId?: number | null, consent: boolean } = { consent: true }) {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')
      this.enrolling = true
      try {
        const res = await useApi()<{ data: ExpoLensEnrollment }>(`/events/${uuid}/expolens/enroll`, {
          method: 'POST',
          body: {
            consent: opts.consent,
            file_id: opts.fileId || undefined,
          },
        })
        this.enrollment = res.data
        return res.data
      } finally {
        this.enrolling = false
      }
    },

    async withdraw() {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')
      await useApi()(`/events/${uuid}/expolens/enroll`, { method: 'DELETE' })
      this.enrollment = {
        consented: false,
        enrolled: false,
        status: 'not_enrolled',
        error: null,
        enrolled_at: null,
        quality_score: null,
      }
      this.myPhotos = []
    },
  },
})

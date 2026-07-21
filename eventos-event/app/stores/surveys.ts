import { defineStore } from 'pinia'

export type SurveyPhase = 'upcoming' | 'ongoing' | 'ended'
export type QuestionType = 'text' | 'textarea' | 'date' | 'select' | 'multiselect' | 'radio' | 'file'

export interface SurveyQuestion {
  id: number
  label: string
  help_text: string | null
  type: QuestionType
  is_required: boolean
  options: { label: string, value: string }[]
}

/** An answer as the API stores it: text/date/file url, or picked option values. */
export type SurveyAnswer = string | string[] | null

/** A survey as the attendee sees it — the organizer's questions plus my state. */
export interface Survey {
  id: number
  title: string
  description: string | null
  phase: SurveyPhase
  is_anonymous: boolean
  opens_at: string | null
  closes_at: string | null
  questions_count: number
  questions: SurveyQuestion[]
  has_responded: boolean
  can_respond: boolean
  my_answers?: Record<number, SurveyAnswer> | null
}

/**
 * Surveys ("Surveys" tab) — the attendee side of Engagement › Surveys. The
 * organizer builds the questionnaire; here it is answered once, through the
 * authed participant routes `/events/{uuid}/my/surveys` (under `my/` so they
 * don't shadow the organizer's own `/events/{uuid}/surveys` listing).
 */
export const useSurveysStore = defineStore('surveys', {
  state: () => ({
    surveys: [] as Survey[],
    loading: false,
    loaded: false,
    error: false,
    filter: 'open' as 'open' | 'answered' | 'all',

    // The survey being filled in / reviewed, one at a time.
    current: null as Survey | null,
    currentLoading: false,
    submitting: false,
  }),

  getters: {
    /** "Open" is what an attendee can still act on; answered ones move aside. */
    shown: (s): Survey[] => s.surveys.filter((v) => {
      if (s.filter === 'answered') return v.has_responded
      if (s.filter === 'open') return v.can_respond
      return true
    }),

    counts: (s): Record<'open' | 'answered' | 'all', number> => ({
      open: s.surveys.filter(v => v.can_respond).length,
      answered: s.surveys.filter(v => v.has_responded).length,
      all: s.surveys.length,
    }),
  },

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    async fetchSurveys() {
      const uuid = this.eventUuid()
      if (!uuid) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const res = await useApi()<{ data: Survey[] }>(`/events/${uuid}/my/surveys`)
        this.surveys = res.data
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    /** Open one survey — the detail call is what carries `my_answers` back. */
    async openSurvey(id: number) {
      const uuid = this.eventUuid()
      if (!uuid) return

      this.current = this.surveys.find(s => s.id === id) ?? null
      this.currentLoading = true
      try {
        const res = await useApi()<{ data: Survey }>(`/events/${uuid}/my/surveys/${id}`)
        this.current = res.data
      } finally {
        this.currentLoading = false
      }
    },

    close() {
      this.current = null
    },

    /** Submit my answers. Throws the API error so the form can show it inline. */
    async submit(id: number, answers: Record<number, SurveyAnswer>) {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')

      this.submitting = true
      try {
        const res = await useApi()<{ data: Survey }>(`/events/${uuid}/my/surveys/${id}/responses`, {
          method: 'POST',
          body: { answers },
        })
        this.current = res.data
        this.surveys = this.surveys.map(s => (s.id === id
          ? { ...s, has_responded: true, can_respond: false }
          : s))
        return res.data
      } finally {
        this.submitting = false
      }
    },

    /** Upload a file answer (same MinIO endpoint as the feed and contests). */
    async uploadFile(file: File): Promise<{ url: string, filename: string | null }> {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')

      const form = new FormData()
      form.append('file', file)
      form.append('collection', 'survey_response')

      const res = await useApi()<{ data: { url: string, filename: string | null } }>(
        `/events/${uuid}/uploads`,
        { method: 'POST', body: form },
      )
      return res.data
    },
  },
})

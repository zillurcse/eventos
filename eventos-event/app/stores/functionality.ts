import { defineStore } from 'pinia'

/** Operations from admin › Communication › Functionality. */
export type FunctionalityOp =
  | 'create_feed_text'
  | 'create_feed_image'
  | 'create_feed_video'
  | 'create_feed_polls'
  | 'create_feed_offering'
  | 'create_feed_looking_for'
  | 'comment_feed_post'
  | 'feed_post_likes'
  | 'create_agenda_post'
  | 'create_agenda_qa'
  | 'create_agenda_polls'
  | 'vote_feed_polls'
  | 'vote_agenda_polls'

export type FunctionalityRole = 'attendee' | 'speaker' | 'exhibitor' | 'sponsor'

export interface FeedTabConfig {
  key: string
  label: string
}

export interface CommunicationPayload {
  role: FunctionalityRole
  operations: Record<FunctionalityOp, boolean>
  moderation: {
    agenda_question: boolean
    create_post: boolean
    create_polls: boolean
  }
  feed_tabs: FeedTabConfig[] | null
}

const ALL_OPS: FunctionalityOp[] = [
  'create_feed_text',
  'create_feed_image',
  'create_feed_video',
  'create_feed_polls',
  'create_feed_offering',
  'create_feed_looking_for',
  'comment_feed_post',
  'feed_post_likes',
  'create_agenda_post',
  'create_agenda_qa',
  'create_agenda_polls',
  'vote_feed_polls',
  'vote_agenda_polls',
]

function defaultOps(allowed = true): Record<FunctionalityOp, boolean> {
  return Object.fromEntries(ALL_OPS.map(op => [op, allowed])) as Record<FunctionalityOp, boolean>
}

/**
 * Communication › Functionality for the signed-in participant's role.
 * Loaded once per event; missing/failed loads default to everything allowed
 * (same as an organizer who never opened the screen).
 */
export const useFunctionalityStore = defineStore('functionality', {
  state: () => ({
    role: 'attendee' as FunctionalityRole,
    operations: defaultOps(true),
    moderation: {
      agenda_question: false,
      create_post: false,
      create_polls: false,
    },
    feedTabs: null as FeedTabConfig[] | null,
    loaded: false,
    loading: false,
  }),

  getters: {
    /** True when the caller may perform the operation (defaults open). */
    can: (s) => (op: FunctionalityOp): boolean => s.operations[op] !== false,

    canComposeAnything: (s): boolean =>
      s.operations.create_feed_text
      || s.operations.create_feed_image
      || s.operations.create_feed_video
      || s.operations.create_feed_polls
      || s.operations.create_feed_offering
      || s.operations.create_feed_looking_for,
  },

  actions: {
    absorb(payload: Partial<CommunicationPayload> | null | undefined) {
      if (!payload) return
      if (payload.role) this.role = payload.role
      if (payload.operations) {
        this.operations = { ...defaultOps(true), ...payload.operations }
      }
      if (payload.moderation) {
        this.moderation = { ...this.moderation, ...payload.moderation }
      }
      if (payload.feed_tabs !== undefined) {
        this.feedTabs = payload.feed_tabs
      }
      this.loaded = true
    },

    async fetch(force = false) {
      if ((this.loaded && !force) || this.loading) return
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return

      this.loading = true
      try {
        const api = useApi()
        const res = await api<{ data: CommunicationPayload }>(`/events/${uuid}/communication`)
        this.absorb(res.data)
      } catch {
        // Fail open — the API still enforces; UI just shows the full controls.
        this.loaded = true
      } finally {
        this.loading = false
      }
    },
  },
})

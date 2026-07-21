import { defineStore } from 'pinia'

export type ContestPhase = 'upcoming' | 'ongoing' | 'ended'
export type ContestType = 'entry' | 'response'

export interface ContestAttachment {
  kind: 'image' | 'video'
  url: string
  name?: string | null
}

/** A contest as the attendee sees it — the organizer's post plus what I may do with it. */
export interface Contest {
  id: string
  title: string
  contest_type: ContestType
  phase: ContestPhase
  description: string | null
  description_file_url: string | null
  description_file_name: string | null
  starts_at: string | null
  ends_at: string | null
  banner_url: string | null
  caption: string | null
  character_limit: number
  points: number
  allow_photos: boolean
  allow_videos: boolean
  allow_selfie: boolean
  attach_mandatory: boolean
  allow_multiple_entries: boolean
  moderated: boolean
  can_see_others_entries: boolean
  can_see_other_comments: boolean
  winner_chooser: 'admin' | 'most_likes'
  winner_number: number
  winning_points: number
  entry_count: number
  my_entry_count: number
  can_enter: boolean
  winners?: ContestEntry[]
}

export interface ContestEntry {
  id: string
  kind: 'entry' | 'comment'
  body: string | null
  attachments: ContestAttachment[]
  status: 'pending' | 'approved' | 'rejected'
  is_winner: boolean
  rank: number | null
  awarded_points: number
  like_count: number
  comment_count: number
  author: string
  author_avatar: string | null
  author_headline: string | null
  is_mine: boolean
  liked: boolean
  created_at: string | null
}

export type EntrySort = 'recent' | 'top'

/**
 * Contests ("Contests" tab) — the attendee side of Engagement › Contests. The
 * listing and every write go through the authed participant routes
 * `/events/{uuid}/contests`, so what comes back is already filtered to what the
 * organizer lets this attendee see (private entries, comment visibility).
 */
export const useContestsStore = defineStore('contests', {
  state: () => ({
    contests: [] as Contest[],
    loading: false,
    loaded: false,
    error: false,
    filter: 'ongoing' as ContestPhase | 'all',

    // Detail view — one contest at a time.
    current: null as Contest | null,
    entries: [] as ContestEntry[],
    entriesLoading: false,
    sort: 'recent' as EntrySort,
    mineOnly: false,
    submitting: false,

    // Comments, keyed by entry id (loaded on demand when a thread is opened).
    comments: {} as Record<string, ContestEntry[]>,
  }),

  getters: {
    shown: (s): Contest[] =>
      s.contests.filter(c => s.filter === 'all' || c.phase === s.filter),

    counts: (s): Record<ContestPhase | 'all', number> => ({
      all: s.contests.length,
      ongoing: s.contests.filter(c => c.phase === 'ongoing').length,
      upcoming: s.contests.filter(c => c.phase === 'upcoming').length,
      ended: s.contests.filter(c => c.phase === 'ended').length,
    }),
  },

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    async fetchContests() {
      const uuid = this.eventUuid()
      if (!uuid) { this.error = true; return }

      this.loading = true
      this.error = false
      try {
        const res = await useApi()<{ data: Contest[] }>(`/events/${uuid}/contests`)
        this.contests = res.data
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    async fetchContest(id: string) {
      const uuid = this.eventUuid()
      if (!uuid) return

      this.loading = true
      this.error = false
      try {
        const res = await useApi()<{ data: Contest }>(`/events/${uuid}/contests/${id}`)
        this.current = res.data
      } catch {
        this.error = true
        this.current = null
      } finally {
        this.loading = false
      }
    },

    async fetchEntries(id: string) {
      const uuid = this.eventUuid()
      if (!uuid) return

      this.entriesLoading = true
      try {
        const res = await useApi()<{ data: ContestEntry[] }>(`/events/${uuid}/contests/${id}/entries`, {
          query: { sort: this.sort, mine: this.mineOnly ? 1 : 0 },
        })
        this.entries = res.data
      } catch {
        this.entries = []
      } finally {
        this.entriesLoading = false
      }
    },

    setSort(sort: EntrySort, id: string) {
      this.sort = sort
      return this.fetchEntries(id)
    },

    setMineOnly(mine: boolean, id: string) {
      this.mineOnly = mine
      return this.fetchEntries(id)
    },

    /** Submit an entry. Throws the API error so the composer can show it inline. */
    async submitEntry(id: string, payload: { body?: string, attachments?: ContestAttachment[] }) {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')

      this.submitting = true
      try {
        const res = await useApi()<{ data: ContestEntry }>(`/events/${uuid}/contests/${id}/entries`, {
          method: 'POST',
          body: payload,
        })
        this.entries.unshift(res.data)
        if (this.current) {
          this.current.my_entry_count += 1
          if (res.data.status === 'approved') this.current.entry_count += 1
          this.current.can_enter = this.current.allow_multiple_entries
        }
        return res.data
      } finally {
        this.submitting = false
      }
    },

    async removeEntry(entryId: string) {
      const uuid = this.eventUuid()
      if (!uuid) return

      await useApi()(`/events/${uuid}/contest-entries/${entryId}`, { method: 'DELETE' })

      const removed = this.entries.find(e => e.id === entryId)
      this.entries = this.entries.filter(e => e.id !== entryId)
      if (this.current && removed) {
        this.current.my_entry_count = Math.max(0, this.current.my_entry_count - 1)
        if (removed.status === 'approved') {
          this.current.entry_count = Math.max(0, this.current.entry_count - 1)
        }
        this.current.can_enter = this.current.phase === 'ongoing'
          && (this.current.allow_multiple_entries || this.current.my_entry_count === 0)
      }
    },

    /** Optimistic like toggle, reconciled with the count the server returns. */
    async toggleLike(entry: ContestEntry) {
      const uuid = this.eventUuid()
      if (!uuid) return

      const before = { liked: entry.liked, like_count: entry.like_count }
      entry.liked = !entry.liked
      entry.like_count += entry.liked ? 1 : -1

      try {
        const res = await useApi()<{ data: { liked: boolean, like_count: number } }>(
          `/events/${uuid}/contest-entries/${entry.id}/like`,
          { method: 'POST' },
        )
        entry.liked = res.data.liked
        entry.like_count = res.data.like_count
      } catch (e) {
        Object.assign(entry, before)
        throw e
      }
    },

    async fetchComments(entryId: string) {
      const uuid = this.eventUuid()
      if (!uuid) return

      const res = await useApi()<{ data: ContestEntry[] }>(`/events/${uuid}/contest-entries/${entryId}/comments`)
      this.comments[entryId] = res.data
    },

    async addComment(entry: ContestEntry, body: string) {
      const uuid = this.eventUuid()
      if (!uuid) return

      const res = await useApi()<{ data: ContestEntry }>(
        `/events/${uuid}/contest-entries/${entry.id}/comments`,
        { method: 'POST', body: { body } },
      )
      this.comments[entry.id] = [...(this.comments[entry.id] ?? []), res.data]
      entry.comment_count += 1
    },

    /** Upload one image/video for an entry (same MinIO endpoint as the feed). */
    async uploadMedia(file: File): Promise<{ url: string, mime_type: string | null, filename: string | null }> {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')

      const form = new FormData()
      form.append('file', file)
      form.append('collection', 'contest_entry')

      const res = await useApi()<{ data: { url: string, mime_type: string | null, filename: string | null } }>(
        `/events/${uuid}/uploads`,
        { method: 'POST', body: form },
      )
      return res.data
    },
  },
})

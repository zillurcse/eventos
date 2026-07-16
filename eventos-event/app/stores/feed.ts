import { defineStore } from 'pinia'

export type FeedType = 'text' | 'image' | 'video' | 'pdf' | 'poll' | 'looking_for' | 'offering'

/** "Filter By" values in the right rail. 'mine' → my posts; else a post type. */
export type FeedFilter = 'all' | 'image' | 'video' | 'pdf' | 'poll' | 'offering' | 'looking_for' | 'mine'

export interface FeedAttachment {
  kind: 'image' | 'video' | 'pdf'
  url: string
  name?: string | null
}

export interface PollOption { id: string, text: string, votes: number }

export interface FeedPoll {
  options: PollOption[]
  allow_multiple: boolean
  total_votes: number
  my_vote: string[]
}

export interface FeedPost {
  id: string
  type: FeedType
  body: string
  visibility: 'public' | 'attendees' | 'group'
  is_pinned: boolean
  status: string
  comment_count: number
  reaction_count: number
  author: string
  author_avatar: string | null
  author_role: 'attendee' | 'organizer'
  is_mine: boolean
  reacted: boolean
  attachments: FeedAttachment[]
  tags: string[]
  poll: FeedPoll | null
  created_at: string | null
}

export interface FeedComment {
  id: number
  body: string
  author: string
  author_avatar: string | null
  author_role: 'attendee' | 'organizer'
  created_at: string | null
}

export interface NewPostPayload {
  type: FeedType
  body?: string
  visibility?: FeedPost['visibility']
  attachments?: FeedAttachment[]
  poll?: { options: string[], allow_multiple: boolean }
  tags?: string[]
}

export interface UploadedMedia {
  id: number
  uuid: string
  url: string
  mime_type: string | null
  filename: string | null
}

export interface FeedAdImage {
  image_url?: string
  url?: string
  redirect_url?: string
  is_active?: boolean
  [k: string]: any
}

export interface FeedAd {
  id: string | number
  title: string
  placement: string
  images: FeedAdImage[]
}

interface Paginated<T> {
  data: T[]
  meta?: { current_page: number, last_page: number }
}

/**
 * The event feed ("Event Feed" tab) — an authenticated, per-event social wall.
 * Supports rich posts: text, image/video/PDF media, polls, and "looking for" /
 * "offering" networking posts. All calls go through useApi() (bearer +
 * subdomain) against the participant routes `/events/{uuid}/feed`.
 */
export const useFeedStore = defineStore('feed', {
  state: () => ({
    posts: [] as FeedPost[],
    page: 1,
    lastPage: 1,
    loading: false,
    loaded: false,
    posting: false,
    error: false,
    filter: 'all' as FeedFilter,
    search: '',
    ads: [] as FeedAd[],
    adsLoaded: false,
  }),

  getters: {
    hasMore: (s): boolean => s.page < s.lastPage,
  },

  actions: {
    eventUuid(): string | null {
      return useSiteStore().event?.uuid ?? null
    },

    async fetchFeed(reset = true) {
      const uuid = this.eventUuid()
      if (!uuid) { this.error = true; return }

      const api = useApi()
      this.loading = true
      this.error = false
      if (reset) { this.page = 1 }
      try {
        const query: Record<string, string | number> = { page: reset ? 1 : this.page }
        if (this.filter === 'mine') query.mine = 1
        else if (this.filter !== 'all') query.type = this.filter
        if (this.search.trim()) query.q = this.search.trim()

        const res = await api<Paginated<FeedPost>>(`/events/${uuid}/feed`, { query })
        this.posts = reset ? res.data : [...this.posts, ...res.data]
        this.page = res.meta?.current_page ?? 1
        this.lastPage = res.meta?.last_page ?? 1
        this.loaded = true
      } catch {
        this.error = true
      } finally {
        this.loading = false
      }
    },

    async loadMore() {
      if (this.loading || !this.hasMore) return
      this.page += 1
      await this.fetchFeed(false)
    },

    /** The "main ads" strip (organizer's AD Managements, targeted to the Event
     *  Feed page) — shown in place of a banner slider at the top of the feed. */
    async fetchAds() {
      if (this.adsLoaded) return
      const sub = useEventSubdomain()
      if (!sub) return
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: { strip: FeedAd[], sidebar: FeedAd[] } }>(`${apiBase}/public/ads`, {
          query: { page: 'feed' },
          headers: { 'X-Event-Subdomain': sub },
        })
        this.ads = res.data.strip
      } catch {
        // Ads are decorative — fail silently, the feed still works without them.
      } finally {
        this.adsLoaded = true
      }
    },

    /** Change the active "Filter By" tab and reload from the top. */
    setFilter(filter: FeedFilter) {
      if (this.filter === filter) return
      this.filter = filter
      this.fetchFeed(true)
    },

    /** Set the search term and reload (callers debounce). */
    setSearch(q: string) {
      this.search = q
      this.fetchFeed(true)
    },

    /** Upload one feed media file (image/video/pdf) → returns its public URL. */
    async uploadMedia(file: File): Promise<UploadedMedia> {
      const uuid = this.eventUuid()
      if (!uuid) throw new Error('No event context')
      const api = useApi()
      const form = new FormData()
      form.append('file', file)
      form.append('collection', 'feed')
      const res = await api<{ data: UploadedMedia }>(`/events/${uuid}/uploads`, {
        method: 'POST',
        body: form,
      })
      return res.data
    },

    /**
     * Upload one media file with live progress (XHR — $fetch can't report upload
     * progress). Returns the started XHR (so the caller can abort) plus a promise
     * that resolves with the uploaded media. Same auth/subdomain headers as useApi.
     */
    uploadMediaProgress(file: File, onProgress: (pct: number) => void): { xhr: XMLHttpRequest, promise: Promise<UploadedMedia> } {
      const uuid = this.eventUuid()
      const { public: { apiBase } } = useRuntimeConfig()
      const auth = useAuthStore()
      const sub = useEventSubdomain()
      const xhr = new XMLHttpRequest()

      const promise = new Promise<UploadedMedia>((resolve, reject) => {
        if (!uuid) { reject(new Error('No event context')); return }
        const form = new FormData()
        form.append('file', file)
        form.append('collection', 'feed')

        xhr.open('POST', `${apiBase}/events/${uuid}/uploads`)
        xhr.setRequestHeader('Accept', 'application/json')
        if (auth.token) xhr.setRequestHeader('Authorization', `Bearer ${auth.token}`)
        if (sub) xhr.setRequestHeader('X-Event-Subdomain', sub)

        xhr.upload.onprogress = (e) => {
          if (e.lengthComputable) onProgress(Math.round((e.loaded / e.total) * 100))
        }
        xhr.onload = () => {
          if (xhr.status >= 200 && xhr.status < 300) {
            try { resolve(JSON.parse(xhr.responseText).data as UploadedMedia) }
            catch { reject(new Error('Unexpected server response.')) }
          } else {
            if (xhr.status === 401) auth.logout()
            let msg = 'Upload failed. Please try again.'
            try {
              const j = JSON.parse(xhr.responseText)
              msg = j.errors?.file?.[0] || j.message || msg
            } catch { /* keep default */ }
            reject(new Error(msg))
          }
        }
        xhr.onerror = () => reject(new Error('Network error during upload.'))
        xhr.onabort = () => reject(new DOMException('aborted', 'AbortError'))
        xhr.send(form)
      })

      return { xhr, promise }
    },

    async createPost(payload: NewPostPayload): Promise<FeedPost | null> {
      const uuid = this.eventUuid()
      if (!uuid) return null
      const api = useApi()
      this.posting = true
      try {
        const res = await api<{ data: FeedPost }>(`/events/${uuid}/feed`, {
          method: 'POST',
          body: {
            type: payload.type,
            body: payload.body ?? '',
            visibility: payload.visibility ?? 'attendees',
            attachments: payload.attachments ?? [],
            poll: payload.poll,
            tags: payload.tags ?? [],
          },
        })
        // Moderated events return the post as `pending` — it must not appear
        // on the wall until an organizer approves it. "My Posts" does show
        // the author their pending/rejected posts, so insert there too.
        if (res.data.status === 'published' || this.filter === 'mine') this.posts.unshift(res.data)
        return res.data
      } finally {
        this.posting = false
      }
    },

    async toggleReaction(post: FeedPost) {
      const uuid = this.eventUuid()
      if (!uuid) return
      // Optimistic: flip locally first, reconcile with the server response.
      post.reacted = !post.reacted
      post.reaction_count += post.reacted ? 1 : -1
      try {
        const api = useApi()
        const res = await api<{ reacted: boolean, reactions: number }>(
          `/events/${uuid}/feed/${post.id}/reactions`,
          { method: 'POST', body: { type: 'like' } },
        )
        post.reacted = res.reacted
        post.reaction_count = res.reactions
      } catch {
        post.reacted = !post.reacted
        post.reaction_count += post.reacted ? 1 : -1
      }
    },

    async votePoll(post: FeedPost, optionId: string) {
      const uuid = this.eventUuid()
      if (!uuid) return
      const api = useApi()
      const res = await api<{ data: FeedPost }>(`/events/${uuid}/feed/${post.id}/poll/vote`, {
        method: 'POST',
        body: { option_id: optionId },
      })
      // Replace the post in place so the poll re-renders with fresh counts.
      const i = this.posts.findIndex(p => p.id === post.id)
      if (i >= 0) this.posts[i] = res.data
    },

    async fetchComments(post: FeedPost): Promise<FeedComment[]> {
      const uuid = this.eventUuid()
      if (!uuid) return []
      const api = useApi()
      const res = await api<{ data: FeedComment[] }>(`/events/${uuid}/feed/${post.id}/comments`)
      return res.data
    },

    async addComment(post: FeedPost, body: string): Promise<FeedComment | null> {
      const uuid = this.eventUuid()
      if (!uuid || !body.trim()) return null
      const api = useApi()
      const res = await api<{ data: FeedComment }>(`/events/${uuid}/feed/${post.id}/comments`, {
        method: 'POST',
        body: { body: body.trim() },
      })
      post.comment_count += 1
      return res.data
    },
  },
})

import { defineStore } from 'pinia'
import type { ReceptionAd } from './reception'

export interface Delegate {
  id: string
  name: string | null
  company: string
  job_title: string
  avatar_url: string | null
  online: boolean
}

/** A delegate who shares my designation and/or company; `match` says which. */
export interface SimilarDelegate extends Delegate {
  match: string
}

/**
 * The delegate (attendee) directory ("Delegates" tab). Authenticated + scoped
 * to the event via useApi() → `/events/{uuid}/delegates`. Search, sort and
 * pagination are SERVER-side (the directory must scale to very large events,
 * so the client only ever holds the pages it has scrolled through). Each row
 * carries a live `online` flag from the presence heartbeat. Sending a
 * connection request reuses the networking connections endpoint (connect()).
 */
export const useDelegatesStore = defineStore('delegates', {
  state: () => ({
    delegates: [] as Delegate[],
    loading: false,
    loadingMore: false,
    loaded: false,
    error: false,
    page: 1,
    hasMore: false,
    q: '',
    sort: 'az' as 'az' | 'za',
    // Bumped per fetch so a slow stale response can't clobber a newer one.
    seq: 0,
    connected: {} as Record<string, 'pending' | 'error'>,

    // "People like you" strip — same designation and/or company as me.
    similar: [] as SimilarDelegate[],
    similarLoading: false,
    similarLoaded: false,

    // Connect modal (opened from a delegate card's Connect action).
    connectTarget: null as Delegate | null,
    connectTab: 'connect' as 'connect' | 'meet',

    // Organizer's AD Managements strip, targeted to the Delegates page.
    ads: [] as ReceptionAd[],
    adsLoaded: false,
  }),

  actions: {
    async fetchDelegates(reset = true) {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) { this.error = true; return }

      const seq = ++this.seq
      if (reset) { this.page = 1; this.loading = true }
      else this.loadingMore = true
      this.error = false

      try {
        const api = useApi()
        const res = await api<{ data: Delegate[], meta: { page: number, has_more: boolean } }>(
          `/events/${uuid}/delegates`,
          { query: { q: this.q || undefined, sort: this.sort, page: this.page } },
        )
        if (seq !== this.seq) return // a newer request superseded this one
        this.delegates = reset ? res.data : [...this.delegates, ...res.data]
        this.hasMore = res.meta?.has_more ?? false
        this.loaded = true
      } catch {
        if (seq === this.seq) this.error = true
      } finally {
        if (seq === this.seq) { this.loading = false; this.loadingMore = false }
      }
    },

    async loadMore() {
      if (!this.hasMore || this.loading || this.loadingMore) return
      this.page += 1
      await this.fetchDelegates(false)
    },

    /** Server-side search — call from a debounced watcher on the input. */
    async setQuery(q: string) {
      if (this.q === q.trim()) return
      this.q = q.trim()
      await this.fetchDelegates()
    },

    async setSort(sort: 'az' | 'za') {
      if (this.sort === sort) return
      this.sort = sort
      await this.fetchDelegates()
    },

    /**
     * The people who share my job title and/or company. Independent of the
     * directory's search/sort — it's about me, not about the query — so it is
     * fetched once and never re-filtered client-side.
     */
    async fetchSimilar() {
      const uuid = useSiteStore().event?.uuid
      if (!uuid || this.similarLoading) return

      this.similarLoading = true
      try {
        const api = useApi()
        const res = await api<{ data: SimilarDelegate[] }>(`/events/${uuid}/delegates/similar`)
        this.similar = res.data
        this.similarLoaded = true
      } catch {
        this.similar = [] // best-effort: the directory below still works
      } finally {
        this.similarLoading = false
      }
    },

    /** Resolve specific delegates by id (bookmarks panel) — no paging. */
    async resolveByIds(ids: string[]): Promise<Delegate[]> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid || !ids.length) return []
      try {
        const api = useApi()
        const res = await api<{ data: Delegate[] }>(`/events/${uuid}/delegates`, {
          query: { ids: ids.slice(0, 200).join(','), per_page: 100 },
        })
        return res.data
      } catch {
        return []
      }
    },

    /** Send a connection request to a delegate (optionally with a message). */
    async connect(delegate: Delegate, message?: string): Promise<boolean> {
      const uuid = useSiteStore().event?.uuid
      if (!uuid) return false
      this.connected[delegate.id] = 'pending'
      try {
        const api = useApi()
        await api(`/events/${uuid}/connections`, {
          method: 'POST',
          body: { to: delegate.id, message: message || undefined },
        })
        return true
      } catch {
        this.connected[delegate.id] = 'error'
        return false
      }
    },

    // ── Connect modal ────────────────────────────────────────────────────
    openConnect(delegate: Delegate, tab: 'connect' | 'meet' = 'connect') {
      this.connectTarget = delegate
      this.connectTab = tab
    },
    closeConnect() { this.connectTarget = null },

    async fetchAds() {
      if (this.adsLoaded) return
      const sub = useEventSubdomain()
      if (!sub) return
      try {
        const { public: { apiBase } } = useRuntimeConfig()
        const res = await $fetch<{ data: { strip: ReceptionAd[], sidebar: ReceptionAd[] } }>(`${apiBase}/public/ads`, {
          query: { page: 'delegates' },
          headers: { 'X-Event-Subdomain': sub },
        })
        this.ads = res.data.strip
      } catch {
        // Ads are decorative — fail silently, the directory still works without them.
      } finally {
        this.adsLoaded = true
      }
    },
  },
})

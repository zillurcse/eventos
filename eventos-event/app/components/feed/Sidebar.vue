<script setup lang="ts">
import type { FeedFilter } from '~/stores/feed'

const feed = useFeedStore()

/**
 * The "Filter By" rail is the organizer's (admin › Navigation & Menu › Allowed
 * Feed Tabs): they choose which filters appear, in what order, and what they are
 * called. The app owns what each one *does* — the store's FeedFilter and the
 * icon — because only it knows how to query them. The two meet on the key.
 *
 * Admin slugs its labels, so "Images" arrives as `images` while the store's
 * filter is `image`; the aliases below reconcile the two vocabularies.
 */
interface FilterMeta { filter: FeedFilter, icon: string }

const FILTER_META: Record<string, FilterMeta> = {
  all: { filter: 'all', icon: 'M4 8h16M4 14h16' },
  images: { filter: 'image', icon: 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M9 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z' },
  image: { filter: 'image', icon: 'M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5M9 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z' },
  video: { filter: 'video', icon: 'M3 6h13v12H3zM16 10l5-3v10l-5-3z' },
  pdf: { filter: 'pdf', icon: 'M7 3h8l4 4v14H7zM15 3v4h4M9 13h6M9 17h6' },
  polls: { filter: 'poll', icon: 'M5 21V10M12 21V4M19 21v-7' },
  poll: { filter: 'poll', icon: 'M5 21V10M12 21V4M19 21v-7' },
  offers: { filter: 'offering', icon: 'M20 12v9H4v-9M2 7h20v5H2zM12 22V7' },
  offering: { filter: 'offering', icon: 'M20 12v9H4v-9M2 7h20v5H2zM12 22V7' },
  looking_for: { filter: 'looking_for', icon: 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM21 21l-5-5' },
  my_posts: { filter: 'mine', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0' },
  mine: { filter: 'mine', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0' },
}

/** Used only when the organizer has never opened the Allowed Feed Tabs screen. */
const DEFAULT_FILTERS: { key: string, label: string }[] = [
  { key: 'all', label: 'All' },
  { key: 'images', label: 'Images' },
  { key: 'video', label: 'Video' },
  { key: 'pdf', label: 'Pdf' },
  { key: 'polls', label: 'Polls' },
  { key: 'offers', label: 'Offers' },
  { key: 'looking_for', label: 'Looking For' },
  { key: 'my_posts', label: 'My Posts' },
]

const site = useSiteStore()

const filters = computed<{ key: FeedFilter, label: string, icon: string }[]>(() => {
  const configured = site.navigation?.feed_tabs ?? []
  const source: { key: string, label: string }[] = configured.length ? configured : DEFAULT_FILTERS

  return source
    // A filter this build cannot actually run is dropped rather than shown as a
    // dead button — unlike a nav tab, there is no "coming soon" page behind it.
    .filter(f => !!FILTER_META[f.key])
    .map(f => ({
      key: FILTER_META[f.key]!.filter,
      label: f.label,
      icon: FILTER_META[f.key]!.icon,
    }))
})

// The organizer can switch off the filter the viewer is currently on (or the
// feed can be loaded with a stale one) — fall back to All rather than leaving
// the rail with nothing highlighted and the feed silently filtered.
watch(filters, (list) => {
  if (list.length && !list.some(f => f.key === feed.filter)) feed.setFilter('all')
}, { immediate: true })
</script>

<template>
  <aside class="rail">
    <div class="card">
      <p class="title">Filter By</p>
      <nav class="list">
        <button
          v-for="f in filters"
          :key="f.key"
          type="button"
          class="item"
          :class="{ on: feed.filter === f.key }"
          @click="feed.setFilter(f.key)"
        >
          <span class="ic"><svg viewBox="0 0 24 24"><path :d="f.icon" /></svg></span>
          {{ f.label }}
        </button>
      </nav>
    </div>
  </aside>
</template>

<style scoped>
.rail { display: flex; flex-direction: column; gap: 24px; padding: 32px; box-sizing: border-box; }
.card { background: #fff; border-radius: 14px; padding: 18px 14px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.title { margin: 0 6px 6px; padding-bottom: 12px; border-bottom: 1px solid #eef0f3; color: #334155; font-weight: 700; font-size: 1rem; }
.list { display: flex; flex-direction: column; gap: 2px; margin-top: 6px; }
.item { display: flex; align-items: center; gap: 14px; border: none; background: none; border-radius: 10px; padding: 11px 10px; cursor: pointer; font: inherit; font-size: .98rem; color: #475569; text-align: left; }
.item:hover { background: #f7f8fa; }
.ic { flex: 0 0 auto; width: 34px; height: 34px; border-radius: 9px; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; justify-content: center; }
.ic svg { width: 19px; height: 19px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.item.on { color: var(--brand-primary); font-weight: 700; }
.item.on .ic { background: var(--brand-primary); color: #fff; }
</style>

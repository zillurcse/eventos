<script setup lang="ts">
definePageMeta({ layout: 'event', middleware: 'auth' })

const feed = useFeedStore()

onMounted(() => {
  if (!feed.loaded) feed.fetchFeed()
  if (!feed.adsLoaded) feed.fetchAds()
})

const emptyLabel = computed(() => {
  if (feed.search.trim()) return 'No posts match your search.'
  if (feed.filter === 'mine') return 'You haven’t posted anything yet.'
  if (feed.filter !== 'all') return 'No posts of this type yet.'
  return 'No posts yet.'
})

// Search lives in the main column here (not the sidebar) — debounce so we
// don't refetch on every keystroke.
const searchTerm = ref<string>(feed.search)
let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(searchTerm, (v: string) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => feed.setSearch(v), 350)
})
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })
</script>

<template>
  <div class="page">
    <div class="grid">
      <div class="col">
        <ReceptionAdStrip v-if="feed.ads.length" :ads="feed.ads" class="banner" />

        <div class="search">
          <input v-model="searchTerm" type="text" placeholder="Search...">
          <svg viewBox="0 0 24 24"><path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" /></svg>
        </div>

        <FeedComposer />

        <div v-if="feed.loading && !feed.loaded" class="state">Loading the feed…</div>
        <div v-else-if="feed.error" class="state">Couldn’t load the feed. Please try again.</div>
        <div v-else-if="!feed.posts.length" class="state">{{ emptyLabel }}</div>

        <div v-else class="list">
          <FeedPostCard v-for="p in feed.posts" :key="p.id" :post="p" />

          <button v-if="feed.hasMore" class="more" type="button" :disabled="feed.loading" @click="feed.loadMore()">
            {{ feed.loading ? 'Loading…' : 'Load more' }}
          </button>
        </div>
      </div>

      <FeedSidebar class="rail" />
    </div>
  </div>
</template>

<style scoped>
.page { display: flex; flex-direction: column; }
.banner { width: 100%; }

.grid { display: grid; grid-template-columns: minmax(0, 1008px) 432px; justify-content: center; align-items: start; }
.col { display: flex; flex-direction: column; gap: 32px; min-width: 0; padding: 32px; box-sizing: border-box; }
.rail { position: sticky; top: 16px; }

.search { position: relative; }
.search input { width: 100%; border: none; background: #fff; border-radius: 12px; padding: 14px 46px 14px 18px; font: inherit; font-size: .95rem; color: #334155; outline: none; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.search input::placeholder { color: #94a3b8; }
.search input:focus { box-shadow: 0 0 0 2px color-mix(in srgb, var(--brand-primary) 40%, transparent); }
.search svg { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; fill: none; stroke: var(--brand-primary); stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.state { background: #fff; border-radius: 14px; padding: 44px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.list { display: flex; flex-direction: column; gap: 16px; }
.more { align-self: center; border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 10px 26px; font-weight: 700; cursor: pointer; }
.more:disabled { opacity: .6; cursor: default; }

@media (max-width: 920px) {
  .grid { grid-template-columns: 1fr; }
  .col { padding: 16px; }
  .rail { position: static; }
}
</style>

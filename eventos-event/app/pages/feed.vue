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
        <ReceptionAdStrip v-if="feed.ads.length" :ads="feed.ads" />

        <div class="search">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3" />
          </svg>
          <input v-model="searchTerm" type="search" placeholder="Search..." aria-label="Search feed">
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
.page {
  display: flex;
  flex-direction: column;
}

.banner {
  width: 100%;
  overflow: hidden;
  border-radius: 12px;
}

.grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(312px, 380px);
  gap: 30px;
  align-items: start;
}

.col {
  display: flex;
  flex-direction: column;
  gap: 24px;
  min-width: 0;
}

.rail {
  position: sticky;
  top: 16px;
}

.search {
  position: relative;
}

.search input {
  width: 100%;
  height: 40px;
  box-sizing: border-box;
  border: 1px solid #d9dbe5;
  background: #fff;
  border-radius: 8px;
  padding: 0 18px 0 56px;
  font: inherit;
  font-size: 1rem;
  color: #303440;
  outline: none;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .02);
}

.search input::placeholder {
  color: #a0a3ab;
}

.search input:focus {
  border-color: var(--brand-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--brand-primary) 13%, transparent);
}

.search svg {
  position: absolute;
  left: 18px;
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  fill: none;
  stroke: #8b8e96;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  pointer-events: none;
}

.state {
  background: #fff;
  border: 1px solid #e5e6ec;
  border-radius: 16px;
  padding: 44px 0;
  text-align: center;
  color: #64748b;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.more {
  align-self: center;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  border-radius: 999px;
  padding: 10px 26px;
  font-weight: 700;
  cursor: pointer;
}

.more:disabled {
  opacity: .6;
  cursor: default;
}

@media (max-width: 920px) {
  .grid {
    grid-template-columns: 1fr;
  }

  .rail {
    position: static;
  }
}
</style>

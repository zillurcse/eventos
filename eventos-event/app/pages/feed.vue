<script setup lang="ts">
definePageMeta({ layout: 'event', middleware: 'auth' })

const feed = useFeedStore()

onMounted(() => { if (!feed.loaded) feed.fetchFeed() })

const emptyLabel = computed(() => {
  if (feed.search.trim()) return 'No posts match your search.'
  if (feed.filter === 'mine') return 'You haven’t posted anything yet.'
  if (feed.filter !== 'all') return 'No posts of this type yet.'
  return 'No posts yet — start the conversation above.'
})
</script>

<template>
  <div class="grid">
    <div class="col">
      <div class="head">
        <h1>Event Feed</h1>
        <p class="sub">Announcements and conversation from attendees and organizers.</p>
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
</template>

<style scoped>
.grid { display: grid; grid-template-columns: minmax(0, 620px) 300px; gap: 20px; justify-content: center; align-items: start; }
.col { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
.rail { position: sticky; top: 16px; }

.head h1 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #1e293b; }
.sub { margin: 4px 0 0; color: #64748b; font-size: .9rem; }
.state { background: #fff; border-radius: 14px; padding: 44px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.list { display: flex; flex-direction: column; gap: 16px; }
.more { align-self: center; border: 1px solid #e2e8f0; background: #fff; color: #475569; border-radius: 999px; padding: 10px 26px; font-weight: 700; cursor: pointer; }
.more:disabled { opacity: .6; cursor: default; }

@media (max-width: 920px) {
  .grid { grid-template-columns: 1fr; }
  .rail { position: static; }
}
</style>

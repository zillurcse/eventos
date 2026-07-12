<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

type Status = 'pending' | 'published' | 'rejected'

const TABS: { key: Status, label: string }[] = [
  { key: 'pending', label: 'Pending' },
  { key: 'published', label: 'Approved' },
  { key: 'rejected', label: 'Rejected' },
]

const tab = ref<Status>('pending')
const search = ref('')
const posts = ref<any[]>([])
const counts = reactive<Record<Status, number>>({ pending: 0, published: 0, rejected: 0 })
const moderate = ref(false)
const loading = ref(true)
const page = ref(1)
const lastPage = ref(1)
const acting = ref('') // uuid of the post a decision is in flight for
const savingModerate = ref(false)

async function load(reset = true) {
  loading.value = reset
  if (reset) page.value = 1
  try {
    const query: Record<string, string | number> = { status: tab.value, page: page.value }
    if (search.value.trim()) query.q = search.value.trim()
    const res = await api<any>(`/events/${id}/feed-moderation`, { query })
    posts.value = reset ? res.data : [...posts.value, ...res.data]
    Object.assign(counts, res.counts)
    moderate.value = !!res.moderate
    page.value = res.meta?.current_page ?? 1
    lastPage.value = res.meta?.last_page ?? 1
  } catch {
    toast.error('Could not load the activity feed.')
  } finally {
    loading.value = false
  }
}

const hasMore = computed(() => page.value < lastPage.value)
async function loadMore() {
  if (hasMore.value) { page.value += 1; await load(false) }
}

function setTab(t: Status) {
  if (tab.value === t) return
  tab.value = t
  load()
}

// Debounced search → reload from page 1.
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => load(), 350)
})
onBeforeUnmount(() => clearTimeout(searchTimer))

async function toggleModerate() {
  if (savingModerate.value) return
  const next = !moderate.value
  moderate.value = next // optimistic
  savingModerate.value = true
  try {
    await api(`/events/${id}/feed-moderation/settings`, { method: 'PATCH', body: { moderate: next } })
    toast.success(next
      ? 'Moderation is on. New posts will wait for your approval.'
      : 'Moderation is off. New posts publish instantly.')
  } catch {
    moderate.value = !next
    toast.error('Could not update the moderation setting.')
  } finally {
    savingModerate.value = false
  }
}

async function decide(p: any, action: 'approve' | 'reject') {
  if (acting.value) return
  acting.value = p.id
  try {
    await api(`/events/${id}/feed-moderation/${p.id}`, { method: 'PATCH', body: { action } })
    // The post left the current tab: drop the card and shift the counters.
    posts.value = posts.value.filter(x => x.id !== p.id)
    counts[p.status as Status] = Math.max(0, counts[p.status as Status] - 1)
    counts[action === 'approve' ? 'published' : 'rejected'] += 1
    toast.success(action === 'approve' ? 'Post approved' : 'Post rejected')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not update the post.')
  } finally {
    acting.value = ''
  }
}

const emptyLabel = computed(() => {
  if (search.value.trim()) return 'No posts match your search.'
  if (tab.value === 'pending') {
    return moderate.value
      ? 'No posts waiting for review.'
      : 'No pending posts. Turn on MODERATE to review posts before they go live.'
  }
  return tab.value === 'published' ? 'No approved posts yet.' : 'No rejected posts.'
})

onMounted(() => load())
</script>

<template>
  <div>
    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
      <div>
        <h1 class="font-bold text-lg text-ink m-0">Manage Activity Feed</h1>
        <p class="muted text-[.85rem] mt-0.5 mb-0">
          Review attendee posts from the event app.
          <template v-if="moderate">New posts are held here until you approve them.</template>
          <template v-else>Posts publish instantly. Enable moderation to review them first.</template>
        </p>
      </div>

      <ActivityFeedModerateSwitch :on="moderate" :saving="savingModerate" @toggle="toggleModerate" />
    </div>

    <!-- ── Tabs + search ──────────────────────────────────────────────── -->
    <div class="flex items-center justify-between gap-3 flex-wrap mb-4">
      <div class="inline-flex bg-white border border-line rounded-xl p-1 gap-1">
        <button
          v-for="t in TABS" :key="t.key"
          class="px-4 py-1.5 rounded-lg text-[.84rem] font-semibold transition-colors inline-flex items-center gap-1.5"
          :class="tab === t.key ? 'bg-[#6352e7] text-white' : 'text-muted hover:text-ink'"
          @click="setTab(t.key)"
        >
          {{ t.label }}
          <span
            class="text-[.7rem] font-bold rounded-full px-1.5 py-px min-w-[20px] text-center"
            :class="tab === t.key ? 'bg-white/20 text-white' : 'bg-[#f1f1f5] text-muted'"
          >{{ counts[t.key] }}</span>
        </button>
      </div>

      <SearchInput v-model="search" placeholder="Search posts…" class="max-w-65" />
    </div>

    <!-- ── Body ───────────────────────────────────────────────────────── -->
    <div v-if="loading" class="card flex items-center justify-center gap-2.5 py-16 text-muted text-[.88rem]">
      <svg class="animate-spin w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
      Loading posts…
    </div>

    <div v-else-if="!posts.length" class="card text-center py-13 px-5">
      <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <p class="muted m-0">{{ emptyLabel }}</p>
    </div>

    <template v-else>
      <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, minmax(320px, 1fr))">
        <ActivityFeedPostCard
          v-for="p in posts" :key="p.id"
          :post="p" :tab="tab" :busy="acting === p.id"
          @approve="decide(p, 'approve')"
          @reject="decide(p, 'reject')"
        />
      </div>

      <div v-if="hasMore" class="text-center mt-5">
        <button class="btn ghost" @click="loadMore">Load more</button>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const route = useRoute()
const api = useApi()
const { $echo } = useNuxtApp()
const id = route.params.id as string

const agenda = ref<any[]>([])
const posts = ref<any[]>([])
const newBody = ref('')
const live = ref(false)

async function loadFeed() {
  try { posts.value = (await api<any>(`/events/${id}/feed`)).data } catch { /* not a participant */ }
}
async function loadAgenda() {
  try { agenda.value = (await api<any>(`/events/${id}/agenda`)).data } catch { /* no perm */ }
}
async function post() {
  if (!newBody.value.trim()) return
  await api(`/events/${id}/feed`, { method: 'POST', body: { body: newBody.value } })
  newBody.value = ''
  await loadFeed()
}

onMounted(async () => {
  await Promise.all([loadFeed(), loadAgenda()])
  try {
    ;($echo as any).channel(`event.${id}.feed`).listen('.feed.post.created', (e: any) => {
      posts.value.unshift({ id: e.id, body: e.body, author: 'Live', comment_count: 0, reaction_count: 0, _live: true })
    })
    live.value = true
  } catch { /* reverb unavailable */ }
})

onBeforeUnmount(() => {
  try { ($echo as any).leave(`event.${id}.feed`) } catch {}
})
</script>

<template>
  <div>
    <h1>Event feed</h1>
    <p class="muted"><span v-if="live" class="live-dot" />{{ live ? 'Live (Reverb)' : 'Offline' }}</p>

    <div class="card">
      <h2>Share with attendees</h2>
      <textarea v-model="newBody" rows="2" placeholder="Say something…" />
      <button class="btn" @click="post">Post</button>
    </div>

    <div v-for="p in posts" :key="p.id" class="feed-post" :class="{ live: p._live }">
      <div>{{ p.body }}</div>
      <div class="by">{{ p.author }} · 💬 {{ p.comment_count }} · 👏 {{ p.reaction_count }}</div>
    </div>
    <p v-if="!posts.length" class="muted">No posts yet — be the first.</p>

    <div v-if="agenda.length" class="card">
      <h2>Agenda</h2>
      <div v-for="s in agenda" :key="s.id" style="padding: 6px 0; border-bottom: 1px solid var(--line);">
        <strong>{{ s.title }}</strong>
        <span class="muted"> — {{ s.starts_at }} [{{ s.timezone }}]</span>
      </div>
    </div>
  </div>
</template>

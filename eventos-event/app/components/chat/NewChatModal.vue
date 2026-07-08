<script setup lang="ts">
import type { ChatPerson } from '~/stores/chat'

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'pick', person: ChatPerson): void
}>()

const q = ref('')
const role = ref('')
const people = ref<ChatPerson[]>([])
const allowedRoles = ref<string[]>([])
const loading = ref(false)

const ROLE_LABEL: Record<string, string> = {
  attendee: 'Attendees', speaker: 'Speakers', exhibitor: 'Exhibitors', sponsor: 'Sponsors',
}

async function search() {
  const uuid = useSiteStore().event?.uuid
  if (!uuid) return
  loading.value = true
  try {
    const api = useApi()
    const query: Record<string, string> = {}
    if (q.value.trim()) query.q = q.value.trim()
    if (role.value) query.role = role.value
    const res = await api<{ data: ChatPerson[], roles: string[] }>(`/events/${uuid}/chat/partners`, { query })
    people.value = res.data
    allowedRoles.value = res.roles
  } finally {
    loading.value = false
  }
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(q, () => {
  clearTimeout(timer)
  timer = setTimeout(search, 300)
})
watch(role, () => search())

onMounted(search)
onBeforeUnmount(() => clearTimeout(timer))
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="modal" role="dialog" aria-label="Start a new chat">
      <header class="head">
        <h3>New chat</h3>
        <button class="x" type="button" aria-label="Close" @click="emit('close')">×</button>
      </header>

      <div class="search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
        <input v-model="q" type="search" placeholder="Search people by name or company…" autofocus>
      </div>

      <div v-if="allowedRoles.length > 1" class="chips">
        <button type="button" class="chip" :class="{ on: !role }" @click="role = ''">All</button>
        <button
          v-for="r in allowedRoles" :key="r"
          type="button" class="chip" :class="{ on: role === r }"
          @click="role = role === r ? '' : r"
        >{{ ROLE_LABEL[r] || r }}</button>
      </div>

      <div class="list">
        <div v-if="loading && !people.length" class="note">Searching…</div>
        <div v-else-if="!people.length" class="note">No people found.</div>

        <button v-for="p in people" :key="p.id" type="button" class="person" @click="emit('pick', p)">
          <span class="av">
            <img v-if="p.avatar_url" :src="p.avatar_url" :alt="p.name">
            <template v-else>{{ initials(p.name) }}</template>
          </span>
          <span class="who">
            <span class="name">{{ p.name }}</span>
            <span class="sub">
              <template v-if="p.job_title">{{ p.job_title }}</template>
              <template v-if="p.job_title && p.company"> · </template>
              <template v-if="p.company">{{ p.company }}</template>
            </span>
          </span>
          <span class="role" :class="p.role">{{ p.role }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; align-items: flex-start; justify-content: center; padding: 8vh 16px 16px; z-index: 60; }
.modal { width: 100%; max-width: 480px; background: #fff; border-radius: 16px; box-shadow: 0 24px 60px rgba(15,23,42,.25); display: flex; flex-direction: column; max-height: 74vh; overflow: hidden; }

.head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px 10px; }
.head h3 { margin: 0; font-size: 1rem; font-weight: 800; color: #1e293b; }
.x { border: none; background: #f1f5f9; color: #64748b; width: 30px; height: 30px; border-radius: 50%; font-size: 1.15rem; line-height: 1; cursor: pointer; }

.search { position: relative; padding: 0 18px 10px; }
.search svg { position: absolute; left: 30px; top: 11px; width: 15px; height: 15px; fill: none; stroke: #94a3b8; stroke-width: 1.8; stroke-linecap: round; }
.search input { width: 100%; border: 1px solid #e2e8f0; border-radius: 999px; padding: 9px 14px 9px 36px; font: inherit; font-size: .88rem; outline: none; color: #334155; }
.search input:focus { border-color: var(--brand-primary); }

.chips { display: flex; flex-wrap: wrap; gap: 7px; padding: 0 18px 10px; }
.chip { border: 1px solid #e2e8f0; background: #fff; color: #64748b; font: inherit; font-size: .76rem; font-weight: 700; border-radius: 999px; padding: 5px 13px; cursor: pointer; }
.chip.on { background: var(--brand-primary); border-color: var(--brand-primary); color: #fff; }

.list { overflow-y: auto; min-height: 180px; border-top: 1px solid #f1f5f9; }
.note { color: #94a3b8; font-size: .84rem; text-align: center; padding: 40px 20px; }

.person { display: flex; align-items: center; gap: 11px; width: 100%; border: none; background: none; padding: 10px 18px; cursor: pointer; text-align: left; font: inherit; }
.person:hover { background: #f8fafc; }
.av { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 50%; background: var(--brand-primary); color: #fff; font-weight: 700; font-size: .78rem; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; }
.av img { width: 100%; height: 100%; object-fit: cover; }
.who { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.name { font-weight: 700; color: #1e293b; font-size: .88rem; }
.sub { color: #94a3b8; font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role { flex: 0 0 auto; font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; border-radius: 999px; padding: 3px 8px; color: #475569; background: #f1f5f9; }
.role.speaker { color: #7c3aed; background: #ede9fe; }
.role.exhibitor { color: #0f766e; background: #ccfbf1; }
.role.sponsor { color: #b45309; background: #fef3c7; }
</style>

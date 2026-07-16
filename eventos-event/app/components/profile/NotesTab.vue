<script setup lang="ts">
import type { NoteType } from '~/stores/notes'

/**
 * "Notes" sub-tab of Profile › My Briefcase — everything the attendee has
 * jotted down against a delegate, session, or speaker from that card's
 * note popup. Resolves each note's target through the same stores the
 * Speakers / Sessions / Delegates pages use (mirrors ProfileBookmarksTab).
 */
const notes = useNotesStore()
const speakers = useSpeakersStore()
const sessions = useSessionsStore()
const delegates = useDelegatesStore()

type Tab = 'delegate' | 'session' | 'speaker'
const tab = ref<Tab>('delegate')

const tabs: Array<{ key: Tab, label: string }> = [
  { key: 'delegate', label: 'Attendees' },
  { key: 'session', label: 'Sessions' },
  { key: 'speaker', label: 'Speakers' },
]

const resolvedDelegates = ref<Awaited<ReturnType<typeof delegates.resolveByIds>>>([])
const delegatesLoading = ref(false)

watch(
  [() => notes.items.delegate, tab],
  async () => {
    if (tab.value !== 'delegate') return
    const ids = notes.items.delegate.map(n => n.target_id)
    delegatesLoading.value = true
    try {
      resolvedDelegates.value = await delegates.resolveByIds(ids)
    } finally {
      delegatesLoading.value = false
    }
  },
  { deep: true, immediate: true },
)

onMounted(() => {
  notes.fetch()
  if (!speakers.loaded && !speakers.loading) speakers.fetchSpeakers()
  if (!sessions.loaded && !sessions.loading) sessions.fetchSessions()
})

const speakerRows = computed(() => notes.items.speaker
  .map(note => ({ note, speaker: speakers.speakers.find(s => s.id === note.target_id) }))
  .filter((r): r is { note: typeof r.note, speaker: NonNullable<typeof r.speaker> } => !!r.speaker))

const sessionRows = computed(() => notes.items.session
  .map(note => ({ note, session: sessions.sessions.find(s => s.id === note.target_id) }))
  .filter((r): r is { note: typeof r.note, session: NonNullable<typeof r.session> } => !!r.session))

const delegateRows = computed(() => notes.items.delegate
  .map(note => ({ note, delegate: resolvedDelegates.value.find(d => d.id === note.target_id) }))
  .filter((r): r is { note: typeof r.note, delegate: NonNullable<typeof r.delegate> } => !!r.delegate))

const loading = computed(() => {
  if (!notes.loaded) return true
  if (tab.value === 'speaker') return speakers.loading && !speakers.loaded
  if (tab.value === 'session') return sessions.loading && !sessions.loaded
  return delegatesLoading.value
})

const empty = computed(() => {
  if (loading.value) return false
  if (tab.value === 'speaker') return !speakerRows.value.length
  if (tab.value === 'session') return !sessionRows.value.length
  return !delegateRows.value.length
})

const copiedId = ref<string | null>(null)
async function copy(id: string, text: string) {
  try {
    await navigator.clipboard?.writeText(text)
    copiedId.value = id
    setTimeout(() => { if (copiedId.value === id) copiedId.value = null }, 1500)
  } catch {
    // clipboard unavailable — ignore
  }
}

function remove(type: NoteType, targetId: string) {
  notes.remove(type, targetId)
}

function subtitle(s: { designation?: string, company?: string }) {
  return [s.designation, s.company].filter(Boolean).join(', ')
}

function fmtWhen(iso: string | null) {
  if (!iso) return ''
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: '2-digit', hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(iso))
}
</script>

<template>
  <div class="notes-tab">
    <nav class="tabs">
      <button
        v-for="t in tabs" :key="t.key" type="button"
        class="tab" :class="{ on: tab === t.key }" @click="tab = t.key"
      >
        {{ t.label }}
      </button>
    </nav>

    <p v-if="loading" class="state">Loading…</p>
    <p v-else-if="empty" class="state">No notes yet — tap the note icon on a card to jot one down.</p>

    <template v-else>
      <div v-if="tab === 'speaker'" class="rows">
        <div v-for="r in speakerRows" :key="r.note.id" class="row">
          <span class="av"><UserAvatar :src="r.speaker.image_url" :name="r.speaker.name" /></span>
          <div class="mid">
            <strong>{{ r.speaker.name }}</strong>
            <small v-if="subtitle(r.speaker)">{{ subtitle(r.speaker) }}</small>
            <p class="text">{{ r.note.text }}</p>
          </div>
          <div class="row-acts">
            <button class="ricon" type="button" title="Copy" @click="copy(r.note.id, r.note.text)">
              <svg v-if="copiedId === r.note.id" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
              <svg v-else viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" /></svg>
            </button>
            <button class="ricon del" type="button" title="Delete" @click="remove('speaker', r.speaker.id)">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
            </button>
          </div>
        </div>
      </div>

      <div v-else-if="tab === 'session'" class="rows">
        <div v-for="r in sessionRows" :key="r.note.id" class="row">
          <div class="mid full">
            <strong>{{ r.session.title }}</strong>
            <small v-if="r.session.starts_at">{{ fmtWhen(r.session.starts_at) }}</small>
            <p class="text">{{ r.note.text }}</p>
          </div>
          <div class="row-acts">
            <button class="ricon" type="button" title="Copy" @click="copy(r.note.id, r.note.text)">
              <svg v-if="copiedId === r.note.id" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
              <svg v-else viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" /></svg>
            </button>
            <button class="ricon del" type="button" title="Delete" @click="remove('session', r.session.id)">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
            </button>
          </div>
        </div>
      </div>

      <div v-else class="rows">
        <div v-for="r in delegateRows" :key="r.note.id" class="row">
          <span class="av"><UserAvatar :src="r.delegate.avatar_url" :name="r.delegate.name" /></span>
          <div class="mid">
            <strong>{{ r.delegate.name }}</strong>
            <small v-if="subtitle({ designation: r.delegate.job_title, company: r.delegate.company })">
              {{ subtitle({ designation: r.delegate.job_title, company: r.delegate.company }) }}
            </small>
            <p class="text">{{ r.note.text }}</p>
          </div>
          <div class="row-acts">
            <button class="ricon" type="button" title="Copy" @click="copy(r.note.id, r.note.text)">
              <svg v-if="copiedId === r.note.id" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
              <svg v-else viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" /></svg>
            </button>
            <button class="ricon del" type="button" title="Delete" @click="remove('delegate', r.delegate.id)">
              <svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
            </button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.tabs { display: flex; gap: 6px; margin-bottom: 18px; }
.tab { border: 1px solid #e5e9f2; background: #fff; border-radius: 999px; padding: 8px 18px; font: inherit; font-size: .84rem; font-weight: 600; color: #64748b; cursor: pointer; }
.tab:hover { color: var(--brand-primary); }
.tab.on { color: #fff; background: var(--brand-primary); border-color: var(--brand-primary); }

.state { color: #94a3b8; font-size: .9rem; padding: 24px 0; text-align: center; }

.rows { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.row { display: flex; align-items: flex-start; gap: 12px; background: #fff; border: 1px solid #eef0f3; border-radius: 12px; padding: 16px; }
.av { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; flex: 0 0 auto; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); }
.mid { min-width: 0; flex: 1; display: flex; flex-direction: column; }
.mid.full { padding-left: 2px; }
.mid strong { color: #1e293b; font-size: .92rem; font-weight: 700; }
.mid small { color: #94a3b8; font-size: .78rem; margin-top: 1px; }
.text { margin: 8px 0 0; color: #475569; font-size: .84rem; line-height: 1.5; }

.row-acts { display: flex; flex-direction: column; gap: 6px; flex: 0 0 auto; }
.ricon { width: 30px; height: 30px; border-radius: 8px; border: none; background: #f4f5f8; color: #64748b; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.ricon:hover { background: #e9ebf5; color: var(--brand-primary); }
.ricon.del:hover { background: #fee2e2; color: #ef4444; }
.ricon svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>

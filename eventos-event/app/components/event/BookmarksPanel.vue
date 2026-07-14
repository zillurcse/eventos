<script setup lang="ts">
import type { BookmarkType } from '~/stores/bookmarks'

const emit = defineEmits<{ close: [] }>()

const bookmarks = useBookmarksStore()
const exhibitors = useExhibitorsStore()
const speakers = useSpeakersStore()
const delegates = useDelegatesStore()
const sessions = useSessionsStore()

type Tab = 'exhibitors' | 'sponsors' | 'speakers' | 'delegates' | 'schedule'
const tab = ref<Tab>('exhibitors')

const tabs: Array<{ key: Tab, label: string }> = [
  { key: 'exhibitors', label: 'Exhibitors' },
  { key: 'sponsors', label: 'Sponsors' },
  { key: 'speakers', label: 'Speakers' },
  { key: 'delegates', label: 'Delegates' },
  { key: 'schedule', label: 'Schedule' },
]

// Delegates are paginated server-side, so saved people are resolved by id
// (a saved delegate may not be in any loaded page). Other directories are
// organizer-bounded full lists.
const savedDelegates = ref<Awaited<ReturnType<typeof delegates.resolveByIds>>>([])
const delegatesLoading = ref(false)

watch(
  [() => bookmarks.saved.delegate, tab],
  async () => {
    if (tab.value !== 'delegates') return
    const ids = Object.entries(bookmarks.saved.delegate).filter(([, on]) => on).map(([id]) => id)
    delegatesLoading.value = true
    try {
      savedDelegates.value = await delegates.resolveByIds(ids)
    } finally {
      delegatesLoading.value = false
    }
  },
  { deep: true },
)

onMounted(() => {
  // Resolve saved ids against the directory stores (each caches itself).
  bookmarks.fetch()
  if (!exhibitors.loaded && !exhibitors.loading) exhibitors.fetchExhibitors()
  if (!speakers.loaded && !speakers.loading) speakers.fetchSpeakers()
  if (!sessions.loaded && !sessions.loading) sessions.fetchSessions()
  window.addEventListener('keydown', onKey)
})
onUnmounted(() => window.removeEventListener('keydown', onKey))

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') emit('close')
}

/** One flattened row shape for every tab. */
interface Row {
  id: string
  type: BookmarkType
  title: string
  sub: string
  image: string | null
  round: boolean
  to: string
  open?: () => void
}

function whenLabel(iso: string | null, endIso: string | null) {
  if (!iso) return 'Time TBA'
  const tz = sessions.eventTimezone
  const part = (opts: Intl.DateTimeFormatOptions, d: Date) =>
    new Intl.DateTimeFormat('en-US', { ...opts, timeZone: tz }).format(d)
  const d = new Date(iso)
  const date = part({ month: 'short', day: 'numeric' }, d).toUpperCase()
  const time = (x: Date) => part({ hour: '2-digit', minute: '2-digit', hour12: true }, x)
  return `${date} | ${endIso ? `${time(d)} - ${time(new Date(endIso))}` : time(d)}`
}

const rows = computed<Row[]>(() => {
  switch (tab.value) {
    case 'exhibitors':
    case 'sponsors': {
      const want = tab.value === 'sponsors' ? 'sponsor' : 'exhibitor'
      return exhibitors.all
        .filter(e => e.type === want && bookmarks.isOn('exhibitor', e.id))
        .map(e => ({
          id: e.id, type: 'exhibitor' as const,
          title: e.name, sub: e.booth ? `Booth ${e.booth}` : (e.category || ''),
          image: e.logo_url, round: false, to: '/exhibitors',
          open: () => exhibitors.open(e),
        }))
    }
    case 'speakers':
      return speakers.speakers
        .filter(s => bookmarks.isOn('speaker', s.id))
        .map(s => ({
          id: s.id, type: 'speaker' as const,
          title: s.name || '?', sub: [s.designation, s.company].filter(Boolean).join(' · '),
          image: s.image_url, round: true, to: '/speakers',
          open: () => speakers.open(s),
        }))
    case 'delegates':
      return savedDelegates.value
        .filter(d => bookmarks.isOn('delegate', d.id))
        .map(d => ({
          id: d.id, type: 'delegate' as const,
          title: d.name || '?', sub: [d.job_title, d.company].filter(Boolean).join(' · '),
          image: d.avatar_url, round: true, to: '/delegates',
        }))
    case 'schedule':
      return sessions.sessions
        .filter(s => bookmarks.isOn('session', s.id))
        .map(s => ({
          id: s.id, type: 'session' as const,
          title: s.title, sub: whenLabel(s.starts_at, s.ends_at),
          // icon_url may hold a catalog icon key (not a URL) for newer sessions;
          // only use it as an image when it actually points somewhere.
          image: (s.icon_url && /^https?:\/\//.test(s.icon_url) ? s.icon_url : null) || s.logo_url, round: false, to: '/sessions',
        }))
  }
  return []
})

const loading = computed(() =>
  !bookmarks.loaded || exhibitors.loading || speakers.loading || delegatesLoading.value || sessions.loading)

function go(row: Row) {
  row.open?.()
  emit('close')
  navigateTo(row.to)
}
</script>

<template>
  <div class="overlay" @click.self="emit('close')">
    <div class="panel" role="dialog" aria-modal="true" aria-label="Bookmarks">
      <header class="head">
        <svg class="bmi" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        <h2>Bookmarks</h2>
        <button class="x" type="button" aria-label="Close" @click="emit('close')">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </header>

      <nav class="tabs">
        <button
          v-for="t in tabs"
          :key="t.key"
          type="button"
          class="tab"
          :class="{ on: tab === t.key }"
          @click="tab = t.key"
        >{{ t.label }}</button>
      </nav>

      <div class="list">
        <p v-if="loading && !rows.length" class="empty">Loading bookmarks…</p>
        <p v-else-if="!rows.length" class="empty">Nothing saved here yet — tap the bookmark icon on a card to save it.</p>

        <article v-for="r in rows" :key="r.id" class="row">
          <button class="hit" type="button" @click="go(r)">
            <!-- A saved list mixes people with things: a speaker with no photo
                 gets their initials, a session with no cover gets the placeholder. -->
            <span class="thumb" :class="{ round: r.round }">
              <UserAvatar
                v-if="r.type === 'speaker' || r.type === 'delegate'"
                :src="r.image" :name="r.title"
              />
              <AppImage v-else :src="r.image" :alt="r.title" />
            </span>
            <span class="txt">
              <strong>{{ r.title }}</strong>
              <small v-if="r.sub">{{ r.sub }}</small>
            </span>
            <svg class="chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
          </button>
          <button class="del" type="button" title="Remove bookmark" @click="bookmarks.toggle(r.type, r.id)">
            <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6" /></svg>
          </button>
        </article>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: stretch; justify-content: flex-end; z-index: 70; }
.panel { background: #fff; width: min(480px, 94vw); height: 100%; display: flex; flex-direction: column; overflow: hidden; box-shadow: -12px 0 40px rgba(15,23,42,.22); animation: slide-in .22s ease; }
@keyframes slide-in { from { transform: translateX(100%); } to { transform: translateX(0); } }

.head { display: flex; align-items: center; gap: 10px; padding: 16px 18px; background: #f1f2f6; }
.bmi { width: 22px; height: 22px; fill: none; stroke: #334155; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
.head h2 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #1e293b; flex: 1; }
.x { border: none; background: #ef4444; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x:hover { background: #dc2626; }
.x svg { width: 14px; height: 14px; fill: none; stroke: #fff; stroke-width: 2.2; stroke-linecap: round; }

.tabs { display: flex; gap: 4px; padding: 0 10px; border-bottom: 1px solid #eef0f3; overflow-x: auto; }
.tab { flex: 0 0 auto; border: none; background: none; padding: 13px 12px 11px; font: inherit; font-size: .88rem; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; }
.tab:hover { color: #475569; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }

.list { flex: 1; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
.empty { margin: 0; padding: 28px 0; text-align: center; color: #94a3b8; font-size: .88rem; }

.row { display: flex; align-items: center; gap: 8px; border: 1px solid #eef0f3; border-radius: 12px; padding: 10px 12px; }
.hit { flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px; border: none; background: none; padding: 0; cursor: pointer; text-align: left; font: inherit; }
.thumb { width: 46px; height: 46px; flex: 0 0 auto; border: 1px solid #eef0f3; border-radius: 8px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.thumb.round { border-radius: 50%; }
.thumb img { width: 100%; height: 100%; object-fit: contain; }
.thumb.round img { object-fit: cover; }
.ini { font-size: .9rem; font-weight: 700; color: color-mix(in srgb, var(--brand-primary) 60%, #cbd5e1); }
.txt { min-width: 0; display: flex; flex-direction: column; gap: 1px; }
.txt strong { font-size: .9rem; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.txt small { color: #64748b; font-size: .78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.chev { width: 17px; height: 17px; margin-left: auto; flex: 0 0 auto; fill: none; stroke: #475569; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.del { flex: 0 0 auto; border: none; background: none; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; color: #ef4444; display: inline-flex; align-items: center; justify-content: center; }
.del:hover { background: #fee2e2; }
.del svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }
</style>

<script setup lang="ts">
import type { BookmarkType } from '~/stores/bookmarks'

const emit = defineEmits<{ close: [] }>()

const bookmarks = useBookmarksStore()
const exhibitors = useExhibitorsStore()
const speakers = useSpeakersStore()
const delegates = useDelegatesStore()
const sessions = useSessionsStore()

type Tab = 'exhibitors' | 'sponsors' | 'speakers' | 'delegates' | 'schedule'
const tab = ref<Tab>('speakers')

const tabs: Array<{ key: Tab, label: string }> = [
  { key: 'speakers', label: 'Speakers' },
  { key: 'schedule', label: 'Sessions' },
  { key: 'exhibitors', label: 'Exhibitors' },
  { key: 'sponsors', label: 'Sponsors' },
  { key: 'delegates', label: 'Delegates' },
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
  place?: string
  image: string | null
  to: string
  open?: () => void
}

function ordinal(n: number) {
  const s = ['th', 'st', 'nd', 'rd'], v = n % 100
  return n + (s[(v - 20) % 10] ?? s[v] ?? s[0] ?? 'th')
}

function whenLabel(iso: string | null, endIso: string | null) {
  if (!iso) return 'Time TBA'
  const tz = deviceTimezone()
  const part = (opts: Intl.DateTimeFormatOptions, d: Date) =>
    new Intl.DateTimeFormat('en-US', { ...opts, timeZone: tz }).format(d)
  const d = new Date(iso)
  const day = Number(part({ day: 'numeric' }, d))
  const mo = part({ month: 'short' }, d)
  const yr = part({ year: 'numeric' }, d)
  const date = `${ordinal(day)} ${mo}, ${yr}`
  const time = (x: Date) => part({ hour: 'numeric', minute: '2-digit', hour12: true }, x)
  return `${date} | ${endIso ? `${time(d)} - ${time(new Date(endIso))}` : time(d)}`
}

const rows = computed<Row[]>(() => {
  switch (tab.value) {
    case 'exhibitors':
    case 'sponsors': {
      const want = tab.value === 'sponsors' ? 'sponsor' : 'exhibitor'
      const label = want === 'sponsor' ? 'Sponsor' : 'Exhibitor'
      return exhibitors.all
        .filter(e => e.type === want && bookmarks.isOn('exhibitor', e.id))
        .map(e => ({
          id: e.id, type: 'exhibitor' as const,
          title: e.name, sub: e.booth ? `${e.booth}, ${label}` : label,
          image: e.logo_url, to: '/exhibitors',
          open: () => exhibitors.open(e),
        }))
    }
    case 'speakers':
      return speakers.speakers
        .filter(s => bookmarks.isOn('speaker', s.id))
        .map(s => ({
          id: s.id, type: 'speaker' as const,
          title: s.name || '?', sub: [s.designation, s.company].filter(Boolean).join(' · '),
          image: s.image_url, to: '/speakers',
          open: () => speakers.open(s),
        }))
    case 'delegates':
      return savedDelegates.value
        .filter(d => bookmarks.isOn('delegate', d.id))
        .map(d => ({
          id: d.id, type: 'delegate' as const,
          title: d.name || '?', sub: [d.job_title, d.company].filter(Boolean).join(' · '),
          image: d.avatar_url, to: '/delegates',
        }))
    case 'schedule':
      return sessions.sessions
        .filter(s => bookmarks.isOn('session', s.id))
        .map(s => ({
          id: s.id, type: 'session' as const,
          title: s.title, sub: whenLabel(s.starts_at, s.ends_at), place: s.session_place || '',
          // icon_url may hold a catalog icon key (not a URL) for newer sessions;
          // only use it as an image when it actually points somewhere.
          image: (s.icon_url && /^https?:\/\//.test(s.icon_url) ? s.icon_url : null) || s.logo_url, to: '/sessions',
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

        <!-- Speakers / Delegates: compact thumb row -->
        <template v-if="tab === 'speakers' || tab === 'delegates'">
          <article v-for="r in rows" :key="r.id" class="row">
            <button class="hit" type="button" @click="go(r)">
              <span class="thumb">
                <UserAvatar :src="r.image" :name="r.title" />
              </span>
              <span class="txt">
                <strong>{{ r.title }}</strong>
                <small v-if="r.sub">{{ r.sub }}</small>
              </span>
            </button>
            <button class="del" type="button" title="Remove bookmark" @click="bookmarks.toggle(r.type, r.id)">
              <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </article>
        </template>

        <!-- Sessions: date/time row + cover photo + title + place -->
        <template v-else-if="tab === 'schedule'">
          <article v-for="r in rows" :key="r.id" class="scard">
            <div class="stop">
              <span class="swhen">{{ r.sub }}</span>
              <button class="del" type="button" title="Remove bookmark" @click="bookmarks.toggle(r.type, r.id)">
                <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
              </button>
            </div>
            <button class="shit" type="button" @click="go(r)">
              <span class="scover"><AppImage :src="r.image" :alt="r.title" /></span>
              <strong class="stitle">{{ r.title }}</strong>
              <span v-if="r.place" class="splace">
                <svg viewBox="0 0 24 24"><path d="M12 21s-7-6.1-7-11a7 7 0 1 1 14 0c0 4.9-7 11-7 11z" /><circle cx="12" cy="10" r="2.5" /></svg>
                {{ r.place }}
              </span>
            </button>
          </article>
        </template>

        <!-- Exhibitors / Sponsors: cover photo card with logo + name strip -->
        <template v-else>
          <article v-for="r in rows" :key="r.id" class="xcard">
            <button class="xhit" type="button" @click="go(r)">
              <span class="xcover">
                <AppImage :src="r.image" :alt="r.title" />
              </span>
              <span class="xbody">
                <span class="xmark"><AppImage :src="r.image" :alt="r.title" /></span>
                <span class="xtext">
                  <strong>{{ r.title }}</strong>
                  <small v-if="r.sub">{{ r.sub }}</small>
                </span>
              </span>
            </button>
            <button class="xdel" type="button" title="Remove bookmark" @click="bookmarks.toggle(r.type, r.id)">
              <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
            </button>
          </article>
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: stretch; justify-content: flex-end; z-index: 70; }
.panel { background: #fff; width: min(480px, 94vw); height: 100%; display: flex; flex-direction: column; overflow: hidden; box-shadow: -12px 0 40px rgba(15,23,42,.22); animation: slide-in .22s ease; }
@keyframes slide-in { from { transform: translateX(100%); } to { transform: translateX(0); } }

.head { display: flex; align-items: center; gap: 10px; padding: 18px 20px 14px; }
.head h2 { margin: 0; font-size: 1.15rem; font-weight: 700; color: #1e293b; flex: 1; }
.x { border: none; background: none; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x:hover { background: #f1f2f6; }
.x svg { width: 15px; height: 15px; fill: none; stroke: #475569; stroke-width: 2; stroke-linecap: round; }

.tabs { display: flex; gap: 4px; padding: 0 16px; border-bottom: 1px solid #eef0f3; overflow-x: auto; }
.tab { flex: 0 0 auto; border: none; background: none; padding: 13px 12px 11px; font: inherit; font-size: .88rem; font-weight: 600; color: #94a3b8; cursor: pointer; border-bottom: 2px solid transparent; white-space: nowrap; }
.tab:hover { color: #475569; }
.tab.on { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }

.list { flex: 1; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; }
.empty { margin: 0; padding: 28px 0; text-align: center; color: #94a3b8; font-size: .88rem; }

.row { display: flex; align-items: center; gap: 8px; border: 1px solid #eef0f3; border-radius: 14px; padding: 10px 14px; transition: border-color .15s ease; }
.row:hover, .row:focus-within { border-color: var(--brand-primary); }
.hit { flex: 1; min-width: 0; display: flex; align-items: center; gap: 12px; border: none; background: none; padding: 0; cursor: pointer; text-align: left; font: inherit; }
.thumb { width: 46px; height: 46px; flex: 0 0 auto; border-radius: 10px; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.thumb img { width: 100%; height: 100%; object-fit: cover; }
.txt { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.txt strong { font-size: .92rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.txt small { color: #94a3b8; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.del { flex: 0 0 auto; border: none; background: none; width: 28px; height: 28px; border-radius: 8px; cursor: pointer; color: #cbd5e1; display: inline-flex; align-items: center; justify-content: center; transition: color .15s ease; }
.row:hover .del, .row:focus-within .del,
.scard:hover .del, .scard:focus-within .del { color: var(--brand-primary); }
.del:hover { background: color-mix(in srgb, var(--brand-primary) 10%, transparent); }
.del svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }

/* ── Sessions: date/time strip + cover photo + title + place ── */
.scard { box-sizing: border-box; height: 282px; display: flex; flex-direction: column; border: 1px solid #eef0f3; border-radius: 16px; padding: 12px 14px 14px; transition: border-color .15s ease; }
.scard:hover, .scard:focus-within { border-color: var(--brand-primary); }
.stop { flex: 0 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.swhen { color: #94a3b8; font-size: .8rem; }
.shit { flex: 1; min-height: 0; display: flex; flex-direction: column; width: 100%; border: none; background: none; padding: 0; margin-top: 10px; cursor: pointer; text-align: left; font: inherit; }
.scover { flex: 0 0 156px; display: block; border-radius: 10px; overflow: hidden; background: #f1f5f9; }
.scover img { width: 100%; height: 100%; object-fit: cover; }
.stitle { flex: 0 0 auto; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; margin-top: 10px; font-size: .92rem; font-weight: 700; color: #1e293b; line-height: 1.35; }
.splace { flex: 0 0 auto; display: flex; align-items: center; gap: 5px; margin-top: 6px; color: #94a3b8; font-size: .8rem; overflow: hidden; }
.splace svg { width: 15px; height: 15px; flex: 0 0 auto; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.splace span, .splace { white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }

/* ── Exhibitors / Sponsors: cover photo + logo/name strip ── */
.xcard { position: relative; box-sizing: border-box; height: 200px; display: flex; flex-direction: column; border: 1px solid #eef0f3; border-radius: 16px; overflow: hidden; transition: border-color .15s ease; }
.xcard:hover, .xcard:focus-within { border-color: var(--brand-primary); }
.xhit { flex: 1; min-height: 0; display: flex; flex-direction: column; width: 100%; border: none; background: none; padding: 0; cursor: pointer; text-align: left; font: inherit; }
.xcover { flex: 1; min-height: 0; display: block; background: #f1f5f9; }
.xcover img { width: 100%; height: 100%; object-fit: cover; }
.xbody { flex: 0 0 auto; display: flex; align-items: center; gap: 10px; padding: 12px 14px 14px; }
.xmark { flex: 0 0 auto; width: 40px; height: 40px; border-radius: 10px; border: 1px solid #eef0f3; background: #f8fafc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
.xmark img { width: 100%; height: 100%; object-fit: cover; }
.xtext { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.xtext strong { font-size: .92rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.xtext small { color: #94a3b8; font-size: .8rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.xdel { position: absolute; top: 12px; right: 12px; width: 30px; height: 30px; border: none; border-radius: 9px; background: #fff; color: #cbd5e1; box-shadow: 0 2px 6px rgba(15,23,42,.18); cursor: pointer; display: inline-flex; align-items: center; justify-content: center; opacity: 0; transition: opacity .15s ease, color .15s ease; }
.xcard:hover .xdel, .xcard:focus-within .xdel { opacity: 1; color: var(--brand-primary); }
.xdel svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }
</style>

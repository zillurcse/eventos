<script setup lang="ts">
definePageMeta({ middleware: 'organizer', title: 'Events', subtitle: 'Create and manage your events' })

const api = useApi()
const events = ref<any[]>([])
const q = ref('')
const error = ref('')
const saving = ref(false)

const showModal = ref(false)
const editingId = ref<string | null>(null)
const form = reactive<{ name: string, format: string, starts_at: string, ends_at: string, cover_file_id: number | null, cover_url: string | null }>(
  { name: '', format: 'venue', starts_at: '', ends_at: '', cover_file_id: null, cover_url: null },
)

const fmtLabel: Record<string, string> = { venue: 'In-person', online: 'Online', hybrid: 'Hybrid' }

async function load() {
  try { events.value = (await api<any>('/events')).data } catch { /* no perm */ }
}

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  return term ? events.value.filter(e => e.name.toLowerCase().includes(term)) : events.value
})
const now = Date.now()
const ongoing = computed(() => filtered.value.filter(e => !e.ends_at || new Date(e.ends_at).getTime() >= now))
const past = computed(() => filtered.value.filter(e => e.ends_at && new Date(e.ends_at).getTime() < now))

// Each section shows a single row (3 cards) until "View All" is expanded.
const PREVIEW = 3
const showAllOngoing = ref(false)
const showAllPast = ref(false)
const ongoingShown = computed(() => showAllOngoing.value ? ongoing.value : ongoing.value.slice(0, PREVIEW))
const pastShown = computed(() => showAllPast.value ? past.value : past.value.slice(0, PREVIEW))

function openCreate() {
  editingId.value = null
  Object.assign(form, { name: '', format: 'venue', starts_at: '', ends_at: '', cover_file_id: null, cover_url: null })
  showModal.value = true
}
function openEdit(e: any) {
  editingId.value = e.id
  Object.assign(form, {
    name: e.name, format: e.format || 'venue',
    starts_at: toLocal(e.starts_at), ends_at: toLocal(e.ends_at),
    cover_file_id: null, cover_url: e.cover_url ?? null,
  })
  showModal.value = true
}
function toLocal(iso: string | null) { return iso ? new Date(iso).toISOString().slice(0, 16) : '' }

async function save() {
  error.value = ''
  saving.value = true
  try {
    const body: any = {
      name: form.name, format: form.format,
      starts_at: form.starts_at || undefined, ends_at: form.ends_at || undefined,
    }
    if (form.cover_file_id) body.cover_file_id = form.cover_file_id
    if (editingId.value) await api(`/events/${editingId.value}`, { method: 'PATCH', body })
    else await api('/events', { method: 'POST', body })
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save the event.'
  } finally {
    saving.value = false
  }
}

async function publish(e: any) { await api(`/events/${e.id}/publish`, { method: 'POST' }); await load() }
async function remove(e: any) {
  if (!confirm(`Delete "${e.name}"?`)) return
  await api(`/events/${e.id}`, { method: 'DELETE' }); await load()
}

const MO = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const WD = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
function fmtDate(d: Date) {
  return `${WD[d.getDay()]}, ${MO[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()}`
}
function dateRange(s: string | null, e: string | null) {
  if (!s) return 'Dates TBD'
  const a = fmtDate(new Date(s))
  return e ? `${a} - ${fmtDate(new Date(e))}` : a
}

onMounted(load)
</script>

<template>
  <div>
    <header class="page-head">
      <h1 class="page-title">My Events</h1>
      <p class="page-lede">
        In just a few simple steps, host a successful event and engage with attendees worldwide.
        This is everything you need to get going.
        <a class="lede-link" href="https://help.expouse.com/events" target="_blank" rel="noopener">Learn More</a>
      </p>
    </header>

    <div class="toolbar">
      <div class="search">
        <AppIcon name="search" />
        <input v-model="q" placeholder="Search...">
      </div>
      <div class="flex-1" />
      <button class="btn" @click="openCreate"><AppIcon name="plus" class="w-4 h-4" /> Create New Event</button>
    </div>

    <EventsEmptyState
      v-if="!events.length"
      title="No events yet"
      description="Create your first event to get started."
      cta-label="Create New Event"
      @cta="openCreate"
    />

    <template v-else>
      <section class="mb-[30px]">
        <div class="section-head">
          <h2 class="section-title">Ongoing</h2>
          <button v-if="ongoing.length > PREVIEW" class="view-all" @click="showAllOngoing = !showAllOngoing">
            {{ showAllOngoing ? 'Show Less' : 'View All' }}
          </button>
        </div>
        <div v-if="ongoing.length" class="cards-grid">
          <EntityCard
            v-for="e in ongoingShown" :key="e.id"
            :title="e.name" :status="e.status" :cover-url="e.cover_url" :seed="e.id" :to="`/org/events/${e.id}`">
            <template #meta>
              {{ fmtLabel[e.format] || e.format }}
              <div class="row"><AppIcon name="calendar" />{{ dateRange(e.starts_at, e.ends_at) }}</div>
            </template>
            <template #menu>
              <EventCardMenu
                :event="e" :to="`/org/events/${e.id}`" allow-publish
                @edit="openEdit(e)" @publish="publish(e)" @delete="remove(e)" />
            </template>
          </EntityCard>
        </div>
        <EventsEmptyState v-else compact description="No ongoing events match your search." />
      </section>

      <section>
        <div class="section-head">
          <h2 class="section-title">Past Events</h2>
          <button v-if="past.length > PREVIEW" class="view-all" @click="showAllPast = !showAllPast">
            {{ showAllPast ? 'Show Less' : 'View All' }}
          </button>
        </div>
        <div v-if="past.length" class="cards-grid">
          <EntityCard
            v-for="e in pastShown" :key="e.id"
            :title="e.name" :status="e.status" :cover-url="e.cover_url" :seed="e.id" :to="`/org/events/${e.id}`">
            <template #meta>
              {{ fmtLabel[e.format] || e.format }}
              <div class="row"><AppIcon name="calendar" />{{ dateRange(e.starts_at, e.ends_at) }}</div>
            </template>
            <template #menu>
              <EventCardMenu :event="e" :to="`/org/events/${e.id}`" @edit="openEdit(e)" @delete="remove(e)" />
            </template>
          </EntityCard>
        </div>
        <EventsEmptyState v-else compact description="No past events match your search." />
      </section>
    </template>

    <Modal v-if="showModal" :title="editingId ? 'Edit event' : 'Create event'" @close="showModal = false">
      <label>Cover image</label>
      <UploadButton :preview="form.cover_url" collection="cover" @uploaded="v => { form.cover_file_id = v.id; form.cover_url = v.url }" />

      <div class="mt-3">
        <label>Event name</label>
        <input v-model="form.name" placeholder="e.g. Tech Expo 2026">
      </div>

      <div>
        <label>Format</label>
        <select v-model="form.format">
          <option value="venue">In-person</option>
          <option value="online">Online</option>
          <option value="hybrid">Hybrid</option>
        </select>
      </div>

      <div class="flex gap-3">
        <div class="flex-1"><label>Starts</label><input v-model="form.starts_at" type="datetime-local"></div>
        <div class="flex-1"><label>Ends</label><input v-model="form.ends_at" type="datetime-local"></div>
      </div>

      <p v-if="error" class="error">{{ error }}</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="showModal = false">Cancel</button>
        <button class="btn" :disabled="saving || !form.name" @click="save">{{ saving ? 'Saving…' : (editingId ? 'Save' : 'Create event') }}</button>
      </div>
    </Modal>
  </div>
</template>

<style scoped>
.page-head {
  margin-bottom: 22px;
}
.page-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--ink);
  letter-spacing: -0.01em;
}
.page-lede {
  margin-top: 6px;
  max-width: 720px;
  color: var(--muted);
  font-size: 0.9rem;
  line-height: 1.5;
}
.lede-link {
  color: var(--brand);
  font-weight: 600;
}
.lede-link:hover {
  text-decoration: underline;
}
.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 8px 0 14px;
}
.section-head .section-title {
  margin: 0;
}
.view-all {
  border: 0;
  background: transparent;
  color: var(--brand);
  font-weight: 650;
  font-size: 0.9rem;
  cursor: pointer;
  padding: 2px 4px;
}
.view-all:hover {
  color: var(--brand-dark);
  text-decoration: underline;
}
</style>

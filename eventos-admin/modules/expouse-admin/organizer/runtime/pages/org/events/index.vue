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
function dateRange(s: string | null, e: string | null) {
  if (!s) return 'Dates TBD'
  const d = new Date(s); const a = `${MO[d.getMonth()]} ${String(d.getDate()).padStart(2, '0')}`
  if (!e) return `${a}, ${d.getFullYear()}`
  const d2 = new Date(e)
  return `${a} - ${MO[d2.getMonth()]} ${String(d2.getDate()).padStart(2, '0')}, ${d2.getFullYear()}`
}

onMounted(load)
</script>

<template>
  <div>
    <div class="toolbar">
      <div class="search">
        <AppIcon name="search" />
        <input v-model="q" placeholder="Search events">
      </div>
      <div class="flex-1" />
      <button class="btn" @click="openCreate"><AppIcon name="plus" class="w-4 h-4" /> Create event</button>
    </div>

    <EventsEmptyState
      v-if="!events.length"
      title="No events yet"
      description="Create your first event to start managing sessions, speakers and attendees."
      cta-label="Create event"
      @cta="openCreate"
    />

    <template v-else>
      <section class="mb-[30px]">
        <h2 class="section-title flex items-center gap-2">
          Ongoing Events
          <span v-if="ongoing.length" class="badge">{{ ongoing.length }}</span>
        </h2>
        <div v-if="ongoing.length" class="cards-grid">
          <EntityCard
            v-for="e in ongoing" :key="e.id"
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
        <h2 class="section-title flex items-center gap-2">
          Past Events
          <span v-if="past.length" class="badge">{{ past.length }}</span>
        </h2>
        <div v-if="past.length" class="cards-grid">
          <EntityCard
            v-for="e in past" :key="e.id"
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

<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface BadgeTemplate {
  id: number
  name: string
  badge_for: string | null
  width: string | null
  height: string | null
  updated_at: string | null
}

// Print-size presets the editor understands (millimetres).
const SIZES: { key: string, label: string, width: number, height: number }[] = [
  { key: 'A6', label: 'A6 — 105 × 148 mm', width: 105, height: 148 },
  { key: 'A7', label: 'A7 — 74 × 105 mm', width: 74, height: 105 },
  { key: 'A4', label: 'A4 — 210 × 297 mm', width: 210, height: 297 },
  { key: 'card', label: 'Card — 85.6 × 54 mm', width: 86, height: 54 },
]

const templates = ref<BadgeTemplate[]>([])
const loading = ref(true)

const showCreate = ref(false)
const saving = ref(false)
const form = reactive({ name: '', badge_for: '', size: 'A6' })

async function load() {
  loading.value = true
  try {
    const res: any = await api(`/events/${id}/badge-designs`)
    templates.value = res?.data ?? res ?? []
  } catch (e) {
    templates.value = []
  } finally {
    loading.value = false
  }
}

function openCreate() {
  form.name = ''
  form.badge_for = ''
  form.size = 'A6'
  showCreate.value = true
}

async function createTemplate() {
  if (!form.name.trim()) return
  saving.value = true
  try {
    const size = SIZES.find(s => s.key === form.size) || SIZES[0]
    const res: any = await api(`/events/${id}/badge-designs`, {
      method: 'POST',
      body: {
        name: form.name.trim(),
        badge_for: form.badge_for.trim() || null,
        measurements_type: 'mm',
        width: String(size.width),
        height: String(size.height),
        badge_json: {},
        layers: [],
      },
    })
    const newId = res?.data?.id
    showCreate.value = false
    // Straight into the editor for the freshly created template.
    if (newId) navigateTo(`/org/events/${id}/badge?design=${newId}`)
    else load()
  } finally {
    saving.value = false
  }
}

function editTemplate(t: BadgeTemplate) {
  navigateTo(`/org/events/${id}/badge?design=${t.id}`)
}

async function deleteTemplate(t: BadgeTemplate) {
  if (!confirm(`Delete badge template “${t.name}”? This cannot be undone.`)) return
  await api(`/badge-designs/${t.id}`, { method: 'DELETE' })
  load()
}

function dims(t: BadgeTemplate) {
  return t.width && t.height ? `${t.width} × ${t.height} mm` : '—'
}
function when(t: BadgeTemplate) {
  if (!t.updated_at) return ''
  try { return new Date(t.updated_at).toLocaleDateString() } catch { return '' }
}

onMounted(load)
</script>

<template>
  <div class="bt">
    <header class="bt-head">
      <div>
        <h1>Badge templates</h1>
        <p>Design the badges printed for this event’s attendees, staff and exhibitors.</p>
      </div>
      <button class="btn-primary" @click="openCreate">
        <Icon name="plus" /> New template
      </button>
    </header>

    <div v-if="loading" class="bt-muted">Loading templates…</div>

    <div v-else-if="!templates.length" class="bt-empty">
      <Icon name="layers" />
      <p>No badge templates yet.</p>
      <button class="btn-primary" @click="openCreate">
        <Icon name="plus" /> Create your first template
      </button>
    </div>

    <div v-else class="bt-grid">
      <div v-for="t in templates" :key="t.id" class="bt-card">
        <div class="bt-preview">
          <Icon name="box" />
        </div>
        <div class="bt-body">
          <h3 :title="t.name">{{ t.name }}</h3>
          <span v-if="t.badge_for" class="bt-pill">{{ t.badge_for }}</span>
          <div class="bt-meta">
            <span>{{ dims(t) }}</span>
            <span v-if="when(t)">· {{ when(t) }}</span>
          </div>
        </div>
        <div class="bt-actions">
          <button class="btn-soft" @click="editTemplate(t)">
            <Icon name="cog" /> Edit
          </button>
          <button class="btn-danger" @click="deleteTemplate(t)" title="Delete">
            <Icon name="logout" />
          </button>
        </div>
      </div>
    </div>

    <Modal v-if="showCreate" title="New badge template" @close="showCreate = false">
      <form class="bt-form" @submit.prevent="createTemplate">
        <label>
          <span>Template name</span>
          <input v-model="form.name" type="text" placeholder="e.g. Attendee Badge" required autofocus />
        </label>
        <label>
          <span>Badge for <small>(optional)</small></span>
          <input v-model="form.badge_for" type="text" placeholder="Attendee, Speaker, Exhibitor…" />
        </label>
        <label>
          <span>Size</span>
          <select v-model="form.size">
            <option v-for="s in SIZES" :key="s.key" :value="s.key">{{ s.label }}</option>
          </select>
        </label>
        <div class="bt-form-actions">
          <button type="button" class="btn-soft" @click="showCreate = false">Cancel</button>
          <button type="submit" class="btn-primary" :disabled="saving || !form.name.trim()">
            {{ saving ? 'Creating…' : 'Create & design' }}
          </button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<style scoped>
.bt { padding: 4px 2px 40px; }
.bt-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; }
.bt-head h1 { font-size: 22px; font-weight: 650; color: var(--ink); }
.bt-head p { color: var(--muted); font-size: 13px; margin-top: 4px; max-width: 52ch; }
.bt-muted { color: var(--muted); padding: 40px 0; text-align: center; }

.bt-empty { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 64px 0; color: var(--muted); text-align: center; }
.bt-empty svg { width: 40px; height: 40px; opacity: .5; }

.bt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 18px; }
.bt-card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s; }
.bt-card:hover { box-shadow: 0 8px 24px rgba(31,36,48,.08); transform: translateY(-2px); }
.bt-preview { height: 132px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--brand-soft), #fff); border-bottom: 1px solid var(--line); }
.bt-preview svg { width: 40px; height: 40px; color: var(--brand); opacity: .7; }
.bt-body { padding: 12px 14px 6px; flex: 1; }
.bt-body h3 { font-size: 14px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bt-pill { display: inline-block; margin-top: 6px; font-size: 11px; font-weight: 600; color: var(--brand-dark); background: var(--brand-soft); padding: 2px 9px; border-radius: 999px; }
.bt-meta { margin-top: 8px; color: var(--faint); font-size: 12px; display: flex; gap: 6px; }
.bt-actions { display: flex; gap: 8px; padding: 10px 14px 14px; }

.btn-primary { display: inline-flex; align-items: center; gap: 6px; background: var(--brand); color: #fff; border: 0; padding: 9px 14px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary:hover { background: var(--brand-dark); }
.btn-primary:disabled { opacity: .6; cursor: not-allowed; }
.btn-soft { display: inline-flex; align-items: center; gap: 6px; background: #f4f5f7; color: var(--ink); border: 1px solid var(--line); padding: 8px 12px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; flex: 1; justify-content: center; }
.btn-soft:hover { background: #eceef1; }
.btn-danger { display: inline-flex; align-items: center; justify-content: center; background: #fff; color: #dc2626; border: 1px solid var(--line); padding: 8px 11px; border-radius: 10px; cursor: pointer; }
.btn-danger:hover { background: #fef2f2; border-color: #fecaca; }
.btn-primary svg, .btn-soft svg, .btn-danger svg { width: 15px; height: 15px; }

.bt-form { display: flex; flex-direction: column; gap: 14px; min-width: 360px; }
.bt-form label { display: flex; flex-direction: column; gap: 6px; font-size: 13px; font-weight: 600; color: var(--ink); }
.bt-form label small { color: var(--faint); font-weight: 400; }
.bt-form input, .bt-form select { border: 1px solid var(--line); border-radius: 10px; padding: 9px 11px; font-size: 13px; font-weight: 400; }
.bt-form input:focus, .bt-form select:focus { outline: none; border-color: var(--brand); }
.bt-form-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 4px; }
.bt-form-actions .btn-soft { flex: 0; }
</style>

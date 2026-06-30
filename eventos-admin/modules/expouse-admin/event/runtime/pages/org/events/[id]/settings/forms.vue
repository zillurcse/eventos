<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const TYPES = ['text', 'email', 'textarea', 'number', 'select', 'checkbox', 'date']
const forms = ref<any[]>([])
const showModal = ref(false)
const editingId = ref<string | null>(null)
const name = ref('')
const rows = ref<{ label: string, type: string, is_required: boolean }[]>([])
const saving = ref(false)
const error = ref('')

const slug = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'field'

async function load() {
  try { forms.value = (await api<any>(`/forms?event=${id}`)).data } catch { /* */ }
}

function openCreate() {
  editingId.value = null; name.value = ''
  rows.value = [{ label: 'Full name', type: 'text', is_required: true }, { label: 'Email', type: 'email', is_required: true }]
  error.value = ''; showModal.value = true
}
async function openEdit(f: any) {
  editingId.value = f.id; name.value = f.name
  const full = (await api<any>(`/forms/${f.id}/edit`)).data
  rows.value = (full.fields || []).map((x: any) => ({ label: x.label || x.key, type: x.type, is_required: !!x.is_required }))
  if (!rows.value.length) rows.value = [{ label: '', type: 'text', is_required: false }]
  error.value = ''; showModal.value = true
}
function addRow() { rows.value.push({ label: '', type: 'text', is_required: false }) }
function removeRow(i: number) { rows.value.splice(i, 1) }

async function save() {
  error.value = ''; saving.value = true
  try {
    const fields = rows.value.filter(r => r.label.trim()).map(r => ({ key: slug(r.label), label: r.label, type: r.type, is_required: r.is_required }))
    if (editingId.value) await api(`/forms/${editingId.value}`, { method: 'PUT', body: { name: name.value, fields } })
    else await api('/forms', { method: 'POST', body: { name: name.value, event: id, target_entity: 'contact', fields } })
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save the form.'
  } finally {
    saving.value = false
  }
}

async function publish(f: any) { await api(`/forms/${f.id}/publish`, { method: 'POST' }); await load() }

onMounted(load)
</script>

<template>
  <div>
    <div class="toolbar">
      <h2 class="section-title m-0">Form Builder</h2>
      <div class="grow flex-1" />
      <button class="btn" @click="openCreate"><Icon name="plus" class="w-4 h-4" /> New form</button>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>Form</th><th>Status</th><th>Fields</th><th /></tr></thead>
        <tbody>
          <tr v-for="f in forms" :key="f.id">
            <td><strong>{{ f.name }}</strong> <span class="muted text-[.8rem]">v{{ f.version }}</span></td>
            <td><span class="badge" :class="f.status">{{ f.status }}</span></td>
            <td>{{ f.fields?.length ?? 0 }}</td>
            <td class="whitespace-nowrap">
              <button class="btn sm ghost" @click="openEdit(f)">Edit</button>
              <button v-if="f.status !== 'published'" class="btn sm" @click="publish(f)">Publish</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-if="!forms.length" class="muted">No forms yet. Create a registration form to collect attendees.</p>
    </div>

    <Modal v-if="showModal" :title="editingId ? 'Edit form' : 'New form'" @close="showModal = false">
      <label>Form name</label>
      <input v-model="name" placeholder="e.g. Registration">
      <label class="mt-2.5 block">Fields</label>
      <div v-for="(r, i) in rows" :key="i" class="flex gap-2 items-center mb-1.5">
        <input v-model="r.label" placeholder="Field label" class="flex-1 m-0">
        <select v-model="r.type" class="w-[120px] m-0">
          <option v-for="t in TYPES" :key="t" :value="t">{{ t }}</option>
        </select>
        <label class="flex items-center gap-1 text-[.8rem] whitespace-nowrap">
          <input v-model="r.is_required" type="checkbox" class="w-auto m-0"> req
        </label>
        <button class="btn sm ghost" @click="removeRow(i)">✕</button>
      </div>
      <button class="btn ghost sm" @click="addRow"><Icon name="plus" class="w-[14px] h-[14px]" /> Add field</button>
      <p v-if="error" class="error">{{ error }}</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="showModal = false">Cancel</button>
        <button class="btn" :disabled="saving || !name" @click="save">{{ saving ? 'Saving…' : 'Save form' }}</button>
      </div>
    </Modal>
  </div>
</template>

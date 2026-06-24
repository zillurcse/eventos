<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const sessions = ref<any[]>([])
const showModal = ref(false)
const form = reactive({ title: '', starts_at: '', ends_at: '' })
const saving = ref(false)
const error = ref('')
const spkFor = ref<string | null>(null)
const spkEmail = ref('')

async function load() {
  try { sessions.value = (await api<any>(`/sessions?event=${id}`)).data } catch { /* */ }
}

async function add() {
  error.value = ''
  saving.value = true
  try {
    await api('/sessions', { method: 'POST', body: { event: id, title: form.title, starts_at: form.starts_at || undefined, ends_at: form.ends_at || undefined } })
    form.title = ''; form.starts_at = ''; form.ends_at = ''
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the session.'
  } finally {
    saving.value = false
  }
}

function openSpeaker(s: any) { spkFor.value = spkFor.value === s.id ? null : s.id; spkEmail.value = '' }
async function addSpeaker(s: any) {
  if (!spkEmail.value) return
  await api(`/sessions/${s.id}/speakers`, { method: 'POST', body: { email: spkEmail.value } })
  spkFor.value = null; spkEmail.value = ''
  await load()
}

function fmt(iso?: string) { return iso ? new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'TBD' }
</script>

<template>
  <div>
    <div class="toolbar">
      <h2 class="section-title m-0">Content Hub — Sessions</h2>
      <div class="grow flex-1" />
      <button class="btn" @click="showModal = true"><Icon name="plus" class="w-4 h-4" /> Add session</button>
    </div>

    <div class="card">
      <table>
        <thead><tr><th>Session</th><th>When</th><th>Speakers</th><th /></tr></thead>
        <tbody>
          <template v-for="s in sessions" :key="s.id">
            <tr>
              <td><strong>{{ s.title }}</strong></td>
              <td class="muted">{{ fmt(s.starts_at) }}</td>
              <td>{{ (s.speakers?.length ?? 0) }}</td>
              <td><button class="btn sm ghost" @click="openSpeaker(s)">Add speaker</button></td>
            </tr>
            <tr v-if="spkFor === s.id">
              <td colspan="4">
                <div class="flex gap-2 items-center">
                  <input v-model="spkEmail" type="email" placeholder="speaker@email.com" class="max-w-[280px] m-0">
                  <button class="btn sm" :disabled="!spkEmail" @click="addSpeaker(s)">Add</button>
                  <button class="btn sm ghost" @click="spkFor = null">Cancel</button>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
      <p v-if="!sessions.length" class="muted">No sessions yet.</p>
    </div>

    <Modal v-if="showModal" title="Add session" @close="showModal = false">
      <label>Title</label>
      <input v-model="form.title" placeholder="e.g. Opening keynote">
      <div class="flex gap-3">
        <div class="flex-1"><label>Starts</label><input v-model="form.starts_at" type="datetime-local"></div>
        <div class="flex-1"><label>Ends</label><input v-model="form.ends_at" type="datetime-local"></div>
      </div>
      <p v-if="error" class="error">{{ error }}</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="showModal = false">Cancel</button>
        <button class="btn" :disabled="saving || !form.title" @click="add">{{ saving ? 'Adding…' : 'Add session' }}</button>
      </div>
    </Modal>
  </div>
</template>

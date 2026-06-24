<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const partners = ref<any[]>([])
const showModal = ref(false)
const form = reactive({ type: 'exhibitor', name: '' })
const saving = ref(false)
const error = ref('')

async function load() {
  try { partners.value = (await api<any>(`/partners?event=${id}`)).data } catch { /* */ }
}

async function add() {
  error.value = ''
  saving.value = true
  try {
    await api('/partners', { method: 'POST', body: { event: id, type: form.type, name: form.name } })
    form.name = ''
    showModal.value = false
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="toolbar">
      <h2 class="section-title m-0">Showcase Arena</h2>
      <div class="grow flex-1" />
      <button class="btn" @click="showModal = true"><Icon name="plus" class="w-4 h-4" /> Add partner</button>
    </div>

    <div v-if="partners.length" class="cards-grid">
      <EntityCard v-for="p in partners" :key="p.id" :title="p.name" :status="p.status" :cover-url="p.logo_url" :seed="p.id">
        <template #meta>
          {{ p.type }}
          <div class="row">{{ p.members_count ?? 0 }} members</div>
        </template>
      </EntityCard>
    </div>
    <p v-else class="muted">No exhibitors or sponsors yet.</p>

    <Modal v-if="showModal" title="Add exhibitor / sponsor" @close="showModal = false">
      <label>Type</label>
      <select v-model="form.type">
        <option value="exhibitor">Exhibitor</option>
        <option value="sponsor">Sponsor</option>
      </select>
      <label>Name</label>
      <input v-model="form.name" placeholder="e.g. Acme Corp">
      <p v-if="error" class="error">{{ error }}</p>
      <div class="modal-actions">
        <button class="btn ghost" @click="showModal = false">Cancel</button>
        <button class="btn" :disabled="saving || !form.name" @click="add">{{ saving ? 'Adding…' : 'Add' }}</button>
      </div>
    </Modal>
  </div>
</template>

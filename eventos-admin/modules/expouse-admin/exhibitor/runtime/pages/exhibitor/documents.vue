<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', feature: 'documents', title: 'Documents', subtitle: 'Brochures & files shown on your booth' })

const api = useApi()
const documents = ref<any[]>([])
const form = reactive({ title: '', url: '', visibility: 'all' })
const suspended = ref(false)
const error = ref('')
const creating = ref(false)

async function load() {
  try {
    documents.value = (await api<any>('/exhibitor/documents')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function add() {
  error.value = ''
  creating.value = true
  try {
    await api('/exhibitor/documents', { method: 'POST', body: { ...form } })
    form.title = ''; form.url = ''; form.visibility = 'all'
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the document.'
  } finally {
    creating.value = false
  }
}

async function remove(d: any) {
  if (!confirm(`Remove "${d.title}"?`)) return
  await api(`/exhibitor/documents/${d.id}`, { method: 'DELETE' })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

    <template v-else>
      <div class="card">
        <h2>Add a document</h2>
        <div class="flex gap-2.5 flex-wrap items-center">
          <input v-model="form.title" placeholder="Title" class="flex-[1_1_200px]">
          <input v-model="form.url" placeholder="File URL (https://…)" class="flex-[1_1_240px]">
          <select v-model="form.visibility" class="py-[9px] px-3 rounded-[10px] border border-[#cbd5e1]">
            <option value="all">Everyone</option>
            <option value="members">Members only</option>
            <option value="private">Private</option>
          </select>
          <button class="btn" :disabled="creating || !form.title" @click="add">{{ creating ? 'Adding…' : 'Add' }}</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>Title</th><th>Link</th><th>Visibility</th><th /></tr>
          </thead>
          <tbody>
            <tr v-for="d in documents" :key="d.id">
              <td><strong>{{ d.title }}</strong></td>
              <td class="muted">
                <a v-if="d.url" :href="d.url" target="_blank" rel="noopener" class="text-brand">Open</a>
                <span v-else>—</span>
              </td>
              <td><span class="badge">{{ d.visibility }}</span></td>
              <td class="whitespace-nowrap"><button class="btn sm danger" @click="remove(d)">Remove</button></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!documents.length" class="muted">No documents yet.</p>
      </div>
    </template>
  </div>
</template>

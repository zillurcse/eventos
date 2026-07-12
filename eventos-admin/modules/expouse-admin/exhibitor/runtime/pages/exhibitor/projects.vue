<script setup lang="ts">
definePageMeta({ middleware: 'exhibitor', feature: 'projects', title: 'Projects', subtitle: 'Manage your booth projects' })

const api = useApi()
const projects = ref<any[]>([])
const form = reactive({ name: '', description: '', status: '' })
const suspended = ref(false)
const error = ref('')
const creating = ref(false)

async function load() {
  try {
    projects.value = (await api<any>('/exhibitor/projects')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

async function add() {
  error.value = ''
  creating.value = true
  try {
    await api('/exhibitor/projects', { method: 'POST', body: { ...form } })
    form.name = ''; form.description = ''; form.status = ''
    await load()
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not add the project.'
  } finally {
    creating.value = false
  }
}

async function remove(p: any) {
  if (!confirm(`Remove "${p.name}"?`)) return
  await api(`/exhibitor/projects/${p.id}`, { method: 'DELETE' })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

    <template v-else>
      <div class="card">
        <h2>Add a project</h2>
        <div class="flex gap-2.5 flex-wrap items-center">
          <input v-model="form.name" placeholder="Project name" class="flex-[1_1_200px]">
          <input v-model="form.description" placeholder="Short description" class="flex-[1_1_260px]">
          <input v-model="form.status" placeholder="Status (e.g. Ongoing)" class="flex-[0_1_170px]">
          <button class="btn" :disabled="creating || !form.name" @click="add">{{ creating ? 'Adding…' : 'Add' }}</button>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
      </div>

      <div class="card">
        <table>
          <thead>
            <tr><th>Project</th><th>Description</th><th>Status</th><th /></tr>
          </thead>
          <tbody>
            <tr v-for="p in projects" :key="p.id">
              <td><strong>{{ p.name }}</strong></td>
              <td class="muted">{{ p.description || '—' }}</td>
              <td><span v-if="p.status" class="badge">{{ p.status }}</span><span v-else class="muted">—</span></td>
              <td class="whitespace-nowrap"><button class="btn sm danger" @click="remove(p)">Remove</button></td>
            </tr>
          </tbody>
        </table>
        <p v-if="!projects.length" class="muted">No projects yet.</p>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'exhibitor', feature: 'projects', title: 'Projects', subtitle: 'Manage your booth projects' })

const api = useApi()
const { upload } = useUpload()

const projects = ref<ExhibitorProject[]>([])
const form = reactive({ ...PROJECT_FORM })
const suspended = ref(false)
const error = ref('')
const saving = ref(false)
const showAdd = ref(false)
const uploadingAttachment = ref(false)

const columns = [
  { key: 'name', label: 'Project' },
  { key: 'description', label: 'Description' },
  { key: 'status', label: 'Status' },
]

async function load() {
  try {
    projects.value = (await api<{ data: ExhibitorProject[] }>('/exhibitor/projects')).data
  } catch (e: any) {
    if (e?.response?.status === 403) suspended.value = true
  }
}

function openAdd() {
  Object.assign(form, PROJECT_FORM)
  error.value = ''
  showAdd.value = true
}

function onImageChange(v: string | string[] | null) {
  form.image_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onImageUploaded(v: { id: number, url: string }) {
  form.image_file_id = v.id
}

async function onAttachment(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploadingAttachment.value = true
  try {
    // Booth uploads go through the exhibitor route, not the organizer /uploads.
    const d = await upload(file, { collection: 'document', path: '/exhibitor/uploads' })
    form.attachment_url = d.url
    form.attachment_file_id = d.id
    form.attachment_name = file.name
  } catch {
    error.value = 'Could not upload the attachment.'
  } finally {
    uploadingAttachment.value = false
    input.value = ''
  }
}

async function add() {
  if (!form.name.trim()) return
  error.value = ''
  saving.value = true
  try {
    const res = await api<{ data: ExhibitorProject }>('/exhibitor/projects', {
      method: 'POST',
      body: {
        name: form.name,
        description: form.description || undefined,
        status: form.status || undefined,
        meta: projectMeta(form),
      },
    })
    projects.value.unshift(res.data)
    showAdd.value = false
    toast.success('Project added')
  } catch (e) {
    error.value = exhibitorError(e, 'Could not add the project.')
  } finally {
    saving.value = false
  }
}

async function remove(p: ExhibitorProject) {
  if (!confirm(`Remove "${p.name}"?`)) return
  try {
    await api(`/exhibitor/projects/${p.id}`, { method: 'DELETE' })
    projects.value = projects.value.filter(x => x.id !== p.id)
  } catch (e) {
    toast.error(exhibitorError(e, 'Could not remove the project.'))
  }
}

onMounted(load)
</script>

<template>
  <div>
    <div v-if="suspended" class="card"><p class="error">This exhibitor account is suspended.</p></div>

    <div v-else class="card">
      <div class="flex items-center justify-between gap-3 mb-4">
        <p class="font-semibold text-[.92rem] m-0 text-ink">Projects</p>
        <button class="btn sm" @click="openAdd">+ ADD PROJECT</button>
      </div>

      <DataTable
        :items="projects"
        :columns="columns"
        row-key="id"
        storage-key="exhibitor-self-projects"
        empty-text="No projects yet."
      >
        <template #cell-name="{ row }">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-lg overflow-hidden shrink-0 bg-[#F0EEFD] flex items-center justify-center">
              <img v-if="row.meta?.image_url" :src="row.meta.image_url" class="w-full h-full object-cover" :alt="row.name">
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="w-4 h-4 text-brand"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
            </div>
            <div class="min-w-0">
              <div class="font-semibold text-ink text-[.88rem] truncate">{{ row.name }}</div>
            </div>
          </div>
        </template>
        <template #cell-description="{ row }">
          <span class="muted text-[.84rem]">{{ row.description || '—' }}</span>
        </template>
        <template #cell-status="{ row }">
          <span v-if="row.status" class="badge">{{ row.status }}</span>
          <span v-else class="muted">—</span>
        </template>
        <template #actions="{ row }">
          <ExhibitorRowDeleteButton title="Remove project" @click="remove(row)" />
        </template>
      </DataTable>
    </div>

    <!-- Add Project drawer — same builder as the organizer Projects tab -->
    <Drawer v-if="showAdd" title="Add Project" back @close="showAdd = false" @back="showAdd = false">
      <label class="block mb-1.5">Project Image</label>
      <ImageField
        :model-value="form.image_url || null"
        :aspect="1"
        collection="exhibitor_logo"
        card-width="96px"
        upload-path="/exhibitor/uploads"
        @update:model-value="onImageChange"
        @uploaded="onImageUploaded"
      />

      <div class="mt-4">
        <AppInput v-model="form.name" label="Project Title" placeholder="Enter Project Title" />
      </div>

      <div class="mt-4">
        <label class="block mb-1.5">Project Details</label>
        <textarea
          v-model="form.description"
          rows="5"
          placeholder="Enter Project Details"
          class="w-full bg-white border border-[#d7dae1] rounded-[11px] px-[13px] py-2.5 text-[.92rem] text-ink outline-none focus:border-brand resize-y"
        />
      </div>

      <div class="mt-4">
        <AppInput v-model="form.status" label="Status" placeholder="e.g. Ongoing" />
      </div>

      <div class="mt-4">
        <AppInput v-model="form.button_label" label="Button Label" placeholder="Enter Button Label" />
      </div>

      <div class="mt-4">
        <AppInput v-model="form.button_url" label="Button URL" placeholder="Enter Button URL" />
      </div>

      <div class="mt-4">
        <label class="block mb-1.5">Attachment</label>
        <div class="flex items-center gap-3 border border-[#d7dae1] rounded-[11px] px-2 py-2 bg-white">
          <label class="btn sm ghost cursor-pointer m-0 shrink-0">
            {{ uploadingAttachment ? 'Uploading…' : 'Choose File' }}
            <input type="file" class="hidden" @change="onAttachment">
          </label>
          <span class="text-[.88rem] truncate" :class="form.attachment_name ? 'text-ink' : 'muted'">
            {{ form.attachment_name || 'No File Chosen' }}
          </span>
        </div>
      </div>

      <p v-if="error" class="error mt-3 mb-0">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-6">
        <button
          class="btn"
          :disabled="saving || uploadingAttachment || !form.name.trim()"
          @click="add"
        >
          {{ saving ? 'Adding…' : 'Add Project' }}
        </button>
        <button class="btn ghost" @click="showAdd = false">Cancel</button>
      </div>
    </Drawer>
  </div>
</template>

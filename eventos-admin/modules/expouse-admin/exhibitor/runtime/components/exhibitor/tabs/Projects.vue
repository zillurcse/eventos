<script setup lang="ts">
const { projectForm, subSaving, subError, projects, addProject, removeProject } = useExhibitorContext()
const { upload } = useUpload()

const showAdd = ref(false)
const uploadingAttachment = ref(false)

function openAdd() {
  // projectForm is shared with the collection (which blanks it after a save);
  // reset here too so a cancelled draft never leaks into the next open.
  Object.assign(projectForm, PROJECT_FORM)
  subError.value = ''
  showAdd.value = true
}

function onImageChange(v: string | string[] | null) {
  projectForm.image_url = (Array.isArray(v) ? v[0] : v) || ''
}
function onImageUploaded(v: { id: number, url: string }) {
  projectForm.image_file_id = v.id
}

async function onAttachment(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploadingAttachment.value = true
  try {
    const d = await upload(file, { collection: 'document' })
    projectForm.attachment_url = d.url
    projectForm.attachment_file_id = d.id
    projectForm.attachment_name = file.name
  } catch {
    subError.value = 'Could not upload the attachment.'
  } finally {
    uploadingAttachment.value = false
    input.value = '' // allow re-selecting the same file
  }
}

async function submit() {
  if (!projectForm.name.trim()) return
  await addProject()
  if (!subError.value) showAdd.value = false
}

const columns = [
  { key: 'name', label: 'Project' },
  { key: 'description', label: 'Description' },
  { key: 'status', label: 'Status' },
]
</script>

<template>
  <div>
    <div class="flex items-center justify-between gap-3 mb-4">
      <p class="font-semibold text-[.92rem] m-0 text-ink">Projects</p>
      <button class="btn sm" @click="openAdd">+ ADD PROJECT</button>
    </div>

    <!-- Projects table -->
    <DataTable
      :items="projects"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-projects"
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
        <ExhibitorRowDeleteButton title="Remove project" @click="removeProject(row)" />
      </template>
    </DataTable>

    <!-- Add Project drawer -->
    <Drawer v-if="showAdd" title="Add Project" back @close="showAdd = false" @back="showAdd = false">
      <!-- Project Image -->
      <label class="block mb-1.5">Project Image</label>
      <ImageField
        :model-value="projectForm.image_url || null"
        :aspect="1"
        collection="exhibitor_logo"
        card-width="96px"
        @update:model-value="onImageChange"
        @uploaded="onImageUploaded"
      />

      <div class="mt-4">
        <AppInput v-model="projectForm.name" label="Project Title" placeholder="Enter Project Title" />
      </div>

      <div class="mt-4">
        <label class="block mb-1.5">Project Details</label>
        <textarea
          v-model="projectForm.description"
          rows="5"
          placeholder="Enter Project Details"
          class="w-full bg-white border border-[#d7dae1] rounded-[11px] px-[13px] py-2.5 text-[.92rem] text-ink outline-none focus:border-brand resize-y"
        />
      </div>

      <div class="mt-4">
        <AppInput v-model="projectForm.status" label="Status" placeholder="e.g. Ongoing" />
      </div>

      <div class="mt-4">
        <AppInput v-model="projectForm.button_label" label="Button Label" placeholder="Enter Button Label" />
      </div>

      <div class="mt-4">
        <AppInput v-model="projectForm.button_url" label="Button URL" placeholder="Enter Button URL" />
      </div>

      <!-- Attachment -->
      <div class="mt-4">
        <label class="block mb-1.5">Attachment</label>
        <div class="flex items-center gap-3 border border-[#d7dae1] rounded-[11px] px-2 py-2 bg-white">
          <label class="btn sm ghost cursor-pointer m-0 shrink-0">
            {{ uploadingAttachment ? 'Uploading…' : 'Choose File' }}
            <input type="file" class="hidden" @change="onAttachment">
          </label>
          <span class="text-[.88rem] truncate" :class="projectForm.attachment_name ? 'text-ink' : 'muted'">
            {{ projectForm.attachment_name || 'No File Chosen' }}
          </span>
        </div>
      </div>

      <p v-if="subError" class="error mt-3 mb-0">{{ subError }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-6">
        <button
          class="btn"
          :disabled="subSaving || uploadingAttachment || !projectForm.name.trim()"
          @click="submit"
        >
          {{ subSaving ? 'Adding…' : 'Add Project' }}
        </button>
        <button class="btn ghost" @click="showAdd = false">Cancel</button>
      </div>
    </Drawer>
  </div>
</template>

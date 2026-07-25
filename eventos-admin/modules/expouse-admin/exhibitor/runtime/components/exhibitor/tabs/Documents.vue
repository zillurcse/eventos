<script setup lang="ts">
import vueFilePond from 'vue-filepond'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size'
import 'filepond/dist/filepond.min.css'

const { docForm, subSaving, subError, documents, addDocument, removeDocument } = useExhibitorContext()
const { upload } = useUpload()

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginFileValidateSize)
const pond = ref<any>(null)
const showAdd = ref(false)
const uploading = ref(false)

// Mirrors the API's `document` collection whitelist (FileUploadController):
// office docs + images, 20 MB cap. The server validates again regardless.
const acceptedTypes = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'text/csv',
  'text/plain',
  'image/png',
  'image/jpeg',
  'image/webp',
]

function openAdd() {
  // docForm is shared with the collection (which blanks it after a save);
  // reset here too so a cancelled draft never leaks into the next open.
  Object.assign(docForm, DOC_FORM)
  subError.value = ''
  showAdd.value = true
  nextTick(() => pond.value?.removeFiles())
}

// Custom FilePond server: push the file through the shared /uploads endpoint
// and drop the returned URL into the form, ready for Add Document.
const pondServer = {
  process: (
    _field: string,
    file: File,
    _meta: any,
    load: (id: string) => void,
    error: (msg: string) => void,
    _progress: any,
    abort: () => void,
  ) => {
    let aborted = false
    uploading.value = true
    upload(file, { collection: 'document' })
      .then((d) => {
        if (aborted) return
        docForm.url = d.url
        docForm.file_id = d.id
        if (!docForm.title) docForm.title = file.name.replace(/\.[^.]+$/, '')
        load(String(d.id))
      })
      .catch(() => { if (!aborted) error('Upload failed') })
      .finally(() => { uploading.value = false })
    return { abort: () => { aborted = true; uploading.value = false; abort() } }
  },
  // Removing the file from the pond before saving clears the pending link.
  revert: (_id: string, load: () => void) => {
    docForm.url = ''
    docForm.file_id = null
    load()
  },
}

async function submit() {
  if (!docForm.title.trim()) return
  await addDocument()
  if (!subError.value) {
    pond.value?.removeFiles()
    showAdd.value = false
  }
}

const columns = [
  { key: 'title', label: 'Document' },
  { key: 'url', label: 'Link' },
]
</script>

<template>
  <div>
    <div class="flex items-center justify-between gap-3 mb-4">
      <p class="font-semibold text-[.92rem] m-0 text-ink">Documents</p>
      <button class="btn sm" @click="openAdd">+ ADD DOCUMENT</button>
    </div>

    <!-- Documents table -->
    <DataTable
      :items="documents"
      :columns="columns"
      row-key="id"
      storage-key="exhibitor-documents"
      empty-text="No documents yet."
    >
      <template #cell-title="{ row }">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-lg bg-brand-soft text-brand flex items-center justify-center shrink-0">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/></svg>
          </div>
          <span class="font-semibold text-ink text-[.88rem] truncate">{{ row.title }}</span>
        </div>
      </template>
      <template #cell-url="{ row }">
        <a v-if="row.url" :href="row.url" target="_blank" rel="noopener" class="text-brand text-[.84rem] hover:underline">{{ row.url }}</a>
        <span v-else class="muted">—</span>
      </template>
      <template #actions="{ row }">
        <ExhibitorRowDeleteButton title="Remove document" @click="removeDocument(row)" />
      </template>
    </DataTable>

    <!-- Add Document drawer -->
    <Drawer v-if="showAdd" title="Add Document" back @close="showAdd = false" @back="showAdd = false">
      <div>
        <AppInput v-model="docForm.title" label="Document Title" placeholder="Enter Document Title" />
      </div>

      <div class="mt-4">
        <label class="block mb-1.5">Upload File</label>
        <FilePond
          ref="pond"
          name="file"
          :server="pondServer"
          :accepted-file-types="acceptedTypes"
          max-file-size="20MB"
          :allow-multiple="false"
          :credits="false"
          label-idle='Drag & drop a file or <span class="filepond--label-action">Browse</span>'
        />
      </div>

      <div class="mt-4">
        <AppInput v-model="docForm.url" label="Link" placeholder="https://… (or paste a link instead)" />
      </div>

      <p v-if="subError" class="error mt-3 mb-0">{{ subError }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-6">
        <button
          class="btn"
          :disabled="subSaving || uploading || !docForm.title.trim()"
          @click="submit"
        >
          {{ uploading ? 'Uploading…' : subSaving ? 'Adding…' : 'Add Document' }}
        </button>
        <button class="btn ghost" @click="showAdd = false">Cancel</button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
/* Blend FilePond's default look into the admin form styling. */
:deep(.filepond--root) { margin-bottom: 0; font-size: .88rem; }
:deep(.filepond--panel-root) { background: #fff; border: 1px dashed var(--line); border-radius: 10px; }
:deep(.filepond--drop-label) { color: var(--muted, #6b7280); }
:deep(.filepond--label-action) { color: var(--brand); text-decoration-color: var(--brand); }
</style>

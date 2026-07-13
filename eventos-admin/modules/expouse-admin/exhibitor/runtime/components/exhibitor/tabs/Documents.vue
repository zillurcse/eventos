<script setup lang="ts">
import vueFilePond from 'vue-filepond'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size'
import 'filepond/dist/filepond.min.css'

const { docForm, subSaving, subError, documents, addDocument, removeDocument } = useExhibitorContext()
const { upload } = useUpload()

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginFileValidateSize)
const pond = ref<any>(null)
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

// Custom FilePond server: push the file through the shared /uploads endpoint
// and drop the returned URL into the form, ready for + ADD DOCUMENT.
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
        if (!docForm.title) docForm.title = file.name.replace(/\.[^.]+$/, '')
        load(String(d.id))
      })
      .catch(() => { if (!aborted) error('Upload failed') })
      .finally(() => { uploading.value = false })
    return { abort: () => { aborted = true; uploading.value = false; abort() } }
  },
  // Removing the file from the pond before saving clears the pending link.
  revert: (_id: string, load: () => void) => { docForm.url = ''; load() },
}

async function submit() {
  await addDocument()
  if (!subError.value) pond.value?.removeFiles()
}

const columns = [
  { key: 'title', label: 'Title' },
  { key: 'url', label: 'Link' },
]
</script>

<template>
  <div>
    <!-- Add document form -->
    <div class="border border-line rounded-xl p-4 mb-5 bg-[#f7f8fa]">
      <p class="font-semibold text-[.92rem] m-0 mb-3 text-ink">Add a document</p>
      <div class="grid gap-2">
        <AppInput v-model="docForm.title" label="Title" placeholder="Document title" />
        <div>
          <label class="block mb-1.5">Upload file</label>
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
        <AppInput v-model="docForm.url" label="Link" placeholder="https://… (or paste a link instead)" />
      </div>
      <div class="flex justify-end mt-3">
        <button class="btn sm" :disabled="subSaving || uploading || !docForm.title" @click="submit">
          {{ uploading ? 'UPLOADING…' : subSaving ? 'ADDING…' : '+ ADD DOCUMENT' }}
        </button>
      </div>
      <p v-if="subError" class="error mt-2 mb-0">{{ subError }}</p>
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
          <div class="w-8 h-8 rounded-lg bg-brand-soft text-brand flex items-center justify-center shrink-0">
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
  </div>
</template>

<style scoped>
/* Blend FilePond's default look into the admin form styling. */
:deep(.filepond--root) { margin-bottom: 0; font-size: .88rem; }
:deep(.filepond--panel-root) { background: #fff; border: 1px dashed var(--line); border-radius: 10px; }
:deep(.filepond--drop-label) { color: var(--muted, #6b7280); }
:deep(.filepond--label-action) { color: var(--brand); text-decoration-color: var(--brand); }
</style>

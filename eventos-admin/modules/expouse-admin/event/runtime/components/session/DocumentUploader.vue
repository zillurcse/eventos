<script setup lang="ts">
import { ref } from 'vue'
import vueFilePond from 'vue-filepond'
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type'
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size'
import 'filepond/dist/filepond.min.css'

interface SessionDocument { name: string; url: string }

const MAX_FILES = 10

// doc / docx / ppt / pptx / pdf — mirrors the API's session_doc whitelist.
const DOC_MIMES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
]

const props = defineProps<{
  modelValue: SessionDocument[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: SessionDocument[]): void
  (e: 'error', message: string): void
}>()

const FilePond = vueFilePond(FilePondPluginFileValidateType, FilePondPluginFileValidateSize)
const pond = ref<any>(null)

const { upload } = useUpload()
const uploading = ref(false)

// The pond is only a dropper: a processed file is appended to modelValue and
// then removed from the pond, so the list below stays the single source.
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
    upload(file, { collection: 'session_doc' })
      .then((r) => {
        if (aborted) return
        emit('update:modelValue', [...props.modelValue, { name: file.name, url: r.url }])
        load(String(r.id))
      })
      .catch(() => { if (!aborted) error('Upload failed') })
      .finally(() => { uploading.value = false })
    return { abort: () => { aborted = true; uploading.value = false; abort() } }
  },
}

function beforeAddFile() {
  if (props.modelValue.length >= MAX_FILES) {
    emit('error', `Maximum ${MAX_FILES} files allowed.`)
    return false
  }
  return true
}

function onProcessFile(err: any, file: any) {
  if (!err) pond.value?.removeFile(file)
}

function remove(i: number) {
  const docs = [...props.modelValue]
  docs.splice(i, 1)
  emit('update:modelValue', docs)
}
</script>

<template>
  <div>
    <FilePond
      ref="pond"
      name="file"
      :server="pondServer"
      :accepted-file-types="DOC_MIMES"
      max-file-size="10MB"
      :allow-multiple="true"
      :max-files="MAX_FILES"
      :before-add-file="beforeAddFile"
      :credits="false"
      label-idle='Only doc, ppt and pdf file allowed — <span class="filepond--label-action">Browse</span>'
      @processfile="onProcessFile"
    />
    <p class="text-[.76rem] text-muted mt-1">Maximum : 10 MB and 10 files are only allowed to upload</p>
    <div v-if="modelValue.length" class="mt-2 flex flex-col gap-1">
      <div
        v-for="(d, i) in modelValue"
        :key="i"
        class="flex items-center justify-between px-3 py-1.5 bg-[#f4f5f7] rounded-lg text-[.84rem]"
      >
        <span class="truncate flex items-center gap-2">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-muted shrink-0"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
          {{ d.name }}
        </span>
        <button class="text-[#dc2626] leading-none text-[1.1rem] px-1" @click="remove(i)">×</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Blend FilePond's default look into the admin form styling. */
:deep(.filepond--root) { margin-bottom: 0; font-size: .86rem; }
:deep(.filepond--panel-root) { background: #fafbfc; border: 2px dashed var(--line); border-radius: 12px; }
:deep(.filepond--drop-label) { color: var(--muted, #6b7280); }
:deep(.filepond--label-action) { color: var(--brand); text-decoration-color: var(--brand); }
</style>

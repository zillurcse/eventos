<script setup lang="ts">
import { ref } from 'vue'

interface SessionDocument { name: string; url: string }

const DOC_EXT = ['doc', 'docx', 'ppt', 'pptx', 'pdf']
const MAX_FILES = 10
const MAX_SIZE  = 10 * 1024 * 1024

const props = defineProps<{
  modelValue: SessionDocument[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: SessionDocument[]): void
  (e: 'error', message: string): void
}>()

const { upload } = useUpload()
const uploading = ref(false)

async function onPick(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files || [])
  input.value = ''
  const docs = [...props.modelValue]
  for (const file of files) {
    if (docs.length >= MAX_FILES) { emit('error', 'Maximum 10 files allowed.'); break }
    const ext = file.name.split('.').pop()?.toLowerCase() || ''
    if (!DOC_EXT.includes(ext)) { emit('error', `Only doc, ppt and pdf files allowed (${file.name}).`); continue }
    if (file.size > MAX_SIZE) { emit('error', `${file.name} exceeds 10 MB.`); continue }
    uploading.value = true
    try {
      const r = await upload(file, { collection: 'session_doc' })
      docs.push({ name: file.name, url: r.url })
      emit('update:modelValue', [...docs])
    } catch {
      emit('error', `Could not upload ${file.name}.`)
    } finally {
      uploading.value = false
    }
  }
}

function remove(i: number) {
  const docs = [...props.modelValue]
  docs.splice(i, 1)
  emit('update:modelValue', docs)
}
</script>

<template>
  <div>
    <label class="flex items-center justify-center gap-2 border-2 border-dashed border-line rounded-xl p-4 cursor-pointer hover:border-brand bg-[#fafbfc] transition-colors">
      <input type="file" multiple accept=".doc,.docx,.ppt,.pptx,.pdf" class="hidden" @change="onPick">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5 text-muted"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
      <span class="text-muted text-[.86rem]">{{ uploading ? 'Uploading…' : 'Only doc, ppt and pdf file allowed' }}</span>
    </label>
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

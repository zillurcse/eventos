<script setup lang="ts">
import { ref, onMounted } from 'vue'

const props = defineProps<{
  modelValue?: string
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()

const editor = ref<HTMLElement | null>(null)

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.modelValue || ''
})

function fmt(cmd: string) {
  document.execCommand(cmd, false)
  sync()
}

function sync() {
  if (editor.value) emit('update:modelValue', editor.value.innerHTML)
}
</script>

<template>
  <div class="border border-line rounded-xl overflow-hidden">
    <div class="flex items-center gap-1 px-2 py-1.5 border-b border-line bg-[#fafbfc]">
      <button type="button" class="rt-btn font-bold" title="Bold" @mousedown.prevent="fmt('bold')">B</button>
      <button type="button" class="rt-btn italic" title="Italic" @mousedown.prevent="fmt('italic')">I</button>
      <button type="button" class="rt-btn underline" title="Underline" @mousedown.prevent="fmt('underline')">U</button>
      <button type="button" class="rt-btn line-through" title="Strikethrough" @mousedown.prevent="fmt('strikeThrough')">S</button>
    </div>
    <div
      ref="editor"
      class="rt-area px-3 py-2 min-h-[120px] text-[.9rem] focus:outline-none"
      contenteditable="true"
      data-ph="Let's write an awesome story!"
      @input="sync"
    />
  </div>
</template>

<style scoped>
.rt-btn {
  width: 28px; height: 28px; border-radius: 6px; color: var(--ink);
  display: flex; align-items: center; justify-content: center; font-size: .9rem;
}
.rt-btn:hover { background: #eceef1; }
.rt-area:empty::before {
  content: attr(data-ph);
  color: var(--faint);
  font-style: italic;
}
</style>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'

const props = defineProps<{
  modelValue?: string
  /** Which toolbar buttons to show, in order. Defaults to the original B/I/U/S set. */
  toolbar?: Array<'bold' | 'italic' | 'underline' | 'strike' | 'bulletList' | 'orderedList' | 'link'>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string): void
}>()

const editor = ref<HTMLElement | null>(null)
const buttons = props.toolbar ?? ['bold', 'italic', 'underline', 'strike']

onMounted(() => {
  if (editor.value) editor.value.innerHTML = props.modelValue || ''
})

// The value can arrive after mount (e.g. an async page load) — keep the editor
// in sync, but don't clobber it while the user is actively typing.
watch(() => props.modelValue, (v) => {
  if (editor.value && v !== editor.value.innerHTML) editor.value.innerHTML = v || ''
})

function fmt(cmd: string) {
  document.execCommand(cmd, false)
  sync()
}

function link() {
  const url = window.prompt('Link URL')
  if (url) document.execCommand('createLink', false, url)
  sync()
}

function sync() {
  if (editor.value) emit('update:modelValue', editor.value.innerHTML)
}
</script>

<template>
  <div class="border border-line rounded-xl overflow-hidden">
    <div class="flex items-center gap-1 px-2 py-1.5 border-b border-line bg-[#fafbfc]">
      <button v-if="buttons.includes('bold')" type="button" class="rt-btn font-bold" title="Bold" @mousedown.prevent="fmt('bold')">B</button>
      <button v-if="buttons.includes('italic')" type="button" class="rt-btn italic" title="Italic" @mousedown.prevent="fmt('italic')">I</button>
      <button v-if="buttons.includes('underline')" type="button" class="rt-btn underline" title="Underline" @mousedown.prevent="fmt('underline')">U</button>
      <button v-if="buttons.includes('strike')" type="button" class="rt-btn line-through" title="Strikethrough" @mousedown.prevent="fmt('strikeThrough')">S</button>
      <span v-if="buttons.some(b => ['bulletList','orderedList','link'].includes(b))" class="w-px h-4 bg-line mx-0.5" />
      <button v-if="buttons.includes('bulletList')" type="button" class="rt-btn" title="Bullet list" @mousedown.prevent="fmt('insertUnorderedList')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/><path d="M9 6h11M9 12h11M9 18h11"/></svg>
      </button>
      <button v-if="buttons.includes('orderedList')" type="button" class="rt-btn" title="Numbered list" @mousedown.prevent="fmt('insertOrderedList')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 4.5v3.5M4 4.5H3M4 4.5H5M4 12h1.5a1 1 0 010 2H4M4 14h1.5a1 1 0 010 2H4"/></svg>
      </button>
      <button v-if="buttons.includes('link')" type="button" class="rt-btn" title="Link" @mousedown.prevent="link">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
      </button>
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

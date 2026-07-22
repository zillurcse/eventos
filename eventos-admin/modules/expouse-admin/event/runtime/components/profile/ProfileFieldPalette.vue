<script setup lang="ts">
/**
 * Left pane of the profile form builder — the field-type palette.
 * Drag a card onto the canvas (dataTransfer carries the type) or click it to
 * append the field at the end.
 */
const emit = defineEmits<{ (e: 'add', type: string): void }>()

const wrap = (inner: string) => `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`

interface PaletteItem { type: string, label: string, svg: string }

const ITEMS: PaletteItem[] = [
  { type: 'text', label: 'Text', svg: '<path d="M4 7V5h16v2"/><path d="M12 5v14"/><path d="M9 19h6"/>' },
  { type: 'textarea', label: 'Text Area', svg: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h10M7 12.5h10M7 16h6"/>' },
  { type: 'email', label: 'Email', svg: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>' },
  { type: 'phone', label: 'Phone', svg: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>' },
  { type: 'date', label: 'Date', svg: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4M16 2v4M3 9.5h18"/>' },
  { type: 'select', label: 'Dropdown', svg: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="m14.5 10.5 2.5 3 2.5-3" transform="translate(-2.5 -1)"/>' },
  { type: 'checkbox', label: 'Checkbox', svg: '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="m8.5 12 2.5 2.5 5-5"/>' },
  { type: 'radio', label: 'Radio', svg: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5" fill="currentColor" stroke="none"/>' },
  { type: 'multiselect', label: 'Multi Select', svg: '<path d="M4 6h10M4 12h10M4 18h10"/><path d="m17 5.5 1.5 1.5 3-3M17 11.5l1.5 1.5 3-3"/>' },
  { type: 'link', label: 'Link', svg: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>' },
  { type: 'file', label: 'File', svg: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>' },
  { type: 'rating', label: 'Rating', svg: '<path d="m12 2.5 2.9 5.9 6.6 1-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5-4.8-4.6 6.6-1z"/>' },
  { type: 'section_break', label: 'Section Break', svg: '<path d="M3 12h18"/><path d="M3 5h18M3 19h18" stroke-dasharray="3 3"/>' },
  { type: 'recaptcha', label: 'reCAPTCHA', svg: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 11.5 2 2 4-4"/>' },
]

function onDragStart(e: DragEvent, type: string) {
  e.dataTransfer?.setData('application/x-field-type', type)
  e.dataTransfer!.effectAllowed = 'copy'
}
</script>

<template>
  <div>
    <h3 class="m-0 text-[1.02rem] font-bold text-ink">Basic Fields</h3>
    <p class="muted text-[.8rem] mt-1 mb-4 leading-relaxed">Drag a field onto the form — or click to add it at the end.</p>

    <div class="grid grid-cols-2 gap-2.5">
      <button
        v-for="it in ITEMS"
        :key="it.type"
        type="button"
        draggable="true"
        class="flex flex-col items-center justify-center gap-2 py-4 px-2 bg-white border border-line rounded-xl cursor-grab text-[#5f6b7a] transition-[border-color,box-shadow,color] duration-150 hover:border-[#6352e7] hover:text-[#6352e7] hover:shadow-[0_2px_10px_rgba(99,82,231,.12)] active:cursor-grabbing"
        :title="`Add ${it.label}`"
        @dragstart="onDragStart($event, it.type)"
        @click="emit('add', it.type)"
      >
        <span v-html="wrap(it.svg)" />
        <span class="text-[.78rem] font-semibold">{{ it.label }}</span>
      </button>
    </div>
  </div>
</template>

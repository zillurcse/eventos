<script setup lang="ts">
import { ref, computed } from 'vue'
import { parseSpreadsheet, parseText } from '../../utils/sheetImport'

/**
 * Bulk-fills a choice field's options from an Excel/CSV sheet or a pasted list.
 * Column A is the label, an optional column B the stored value — that keeps
 * existing answers intact when an organizer relabels a list they exported.
 */
const props = defineProps<{ existing: number }>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'apply', options: { label: string, value?: string }[], mode: 'replace' | 'append'): void
}>()

const HEADER_WORDS = ['label', 'option', 'options', 'name', 'title', 'text']

const pasted = ref('')
const fileName = ref('')
const error = ref('')
const dragging = ref(false)
const skipHeader = ref(false)
const mode = ref<'replace' | 'append'>('replace')
const rows = ref<string[][]>([])
const fileInput = ref<HTMLInputElement>()

/** First cell looks like a column heading rather than a real choice. */
const looksLikeHeader = computed(() => {
  const first = rows.value[0]?.[0]?.trim().toLowerCase() || ''
  return HEADER_WORDS.includes(first)
})

const options = computed(() => {
  const source = skipHeader.value ? rows.value.slice(1) : rows.value
  const seen = new Set<string>()
  const out: { label: string, value?: string }[] = []

  for (const cells of source) {
    const label = (cells[0] ?? '').trim()
    if (!label) continue
    const value = (cells[1] ?? '').trim()
    // A sheet that repeats a label would create options no answer can tell apart.
    const dedupe = (value || label).toLowerCase()
    if (seen.has(dedupe)) continue
    seen.add(dedupe)
    out.push(value ? { label, value } : { label })
  }

  return out
})

function ingest(grid: string[][]) {
  rows.value = grid
  skipHeader.value = looksLikeHeader.value
  if (!options.value.length) error.value = 'Nothing readable in that — the first column should hold one option per row.'
}

async function readFile(file: File) {
  error.value = ''
  fileName.value = file.name
  pasted.value = ''
  try {
    ingest(await parseSpreadsheet(file))
  } catch (e: any) {
    rows.value = []
    error.value = e?.message || 'That file could not be read.'
  }
}

function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (file) readFile(file)
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const file = e.dataTransfer?.files?.[0]
  if (file) readFile(file)
}

function onPaste() {
  error.value = ''
  fileName.value = ''
  if (fileInput.value) fileInput.value.value = ''
  ingest(pasted.value.trim() ? parseText(pasted.value) : [])
  if (!pasted.value.trim()) { rows.value = []; error.value = '' }
}

function apply() {
  if (!options.value.length) return
  emit('apply', options.value, mode.value)
}

/** A file they can open in Excel, fill in and upload straight back. */
function downloadSample() {
  const csv = 'Label,Value\nOption one,option_one\nOption two,option_two\nOption three,\n'
  const url = URL.createObjectURL(new Blob([`﻿${csv}`], { type: 'text/csv;charset=utf-8' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'options-sample.csv'
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <!-- Teleported: the properties pane is `sticky`, which creates a stacking
       context a fixed overlay inside it cannot escape. -->
  <Teleport to="body">
  <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-200 p-4" @click.self="emit('close')">
    <div class="bg-white rounded-2xl w-full max-w-lg max-h-[86vh] flex flex-col overflow-hidden">
      <div class="flex items-start justify-between p-5 border-b border-line">
        <div>
          <div class="font-bold text-[1.05rem] text-ink">Import options</div>
          <div class="muted text-[.84rem]">Upload an Excel or CSV sheet, or paste a list</div>
        </div>
        <button class="text-muted hover:text-ink text-[1.3rem] leading-none" @click="emit('close')">×</button>
      </div>

      <div class="p-5 overflow-y-auto flex-1">
        <!-- Upload -->
        <div
          class="border-2 border-dashed rounded-xl px-4 py-7 text-center cursor-pointer transition-colors"
          :class="dragging ? 'border-[#6352e7] bg-[#f6f4ff]' : 'border-[#d7dae1] hover:border-[#c9c2f5]'"
          @click="fileInput?.click()"
          @dragover.prevent="dragging = true"
          @dragleave="dragging = false"
          @drop.prevent="onDrop"
        >
          <div class="text-[.9rem] font-semibold text-ink">
            {{ fileName || 'Drop a file here, or click to browse' }}
          </div>
          <div class="muted text-[.76rem] mt-1">Excel (.xlsx), CSV or TSV — first column = option label</div>
          <input ref="fileInput" type="file" accept=".xlsx,.csv,.tsv,.txt" class="hidden" @change="onFile">
        </div>

        <button type="button" class="text-[#6352e7] text-[.8rem] font-semibold bg-transparent border-none cursor-pointer p-0 mt-2 hover:underline" @click="downloadSample">
          Download sample file
        </button>

        <div class="flex items-center gap-3 my-4">
          <div class="h-px bg-line flex-1" /><span class="muted text-[.76rem]">or paste</span><div class="h-px bg-line flex-1" />
        </div>

        <textarea
          v-model="pasted" rows="5" class="m-0 w-full text-[.85rem]"
          placeholder="One option per line&#10;Optionally: Label, stored_value"
          @input="onPaste"
        />

        <p v-if="error" class="text-[.78rem] text-[#b91c1c] bg-[#fef2f2] border border-[#fecaca] rounded-lg px-3 py-2 mt-3 mb-0">
          {{ error }}
        </p>

        <!-- Preview -->
        <template v-if="rows.length">
          <label v-if="looksLikeHeader || skipHeader" class="flex items-center gap-2 cursor-pointer select-none text-[.83rem] font-medium mt-4">
            <input v-model="skipHeader" type="checkbox" class="w-4 h-4 m-0 accent-[#6352e7]"> First row is a header
          </label>

          <div class="font-bold text-[.88rem] mt-4 mb-2">
            {{ options.length }} option{{ options.length === 1 ? '' : 's' }} found
          </div>
          <div class="border border-line rounded-xl max-h-52 overflow-y-auto divide-y divide-[#f1f2f5]">
            <div v-for="(o, i) in options" :key="i" class="flex items-center gap-2 px-3 py-2 text-[.83rem]">
              <span class="text-faint text-[.74rem] w-5 shrink-0">{{ i + 1 }}</span>
              <span class="flex-1 truncate">{{ o.label }}</span>
              <span v-if="o.value" class="muted text-[.72rem] font-mono truncate max-w-36">{{ o.value }}</span>
            </div>
          </div>

          <div v-if="existing" class="flex items-center gap-5 mt-4">
            <label class="flex items-center gap-2 cursor-pointer select-none text-[.85rem] font-medium">
              <input type="radio" :checked="mode === 'replace'" class="w-4 h-4 m-0 accent-[#6352e7]" @change="mode = 'replace'">
              Replace the {{ existing }} existing
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none text-[.85rem] font-medium">
              <input type="radio" :checked="mode === 'append'" class="w-4 h-4 m-0 accent-[#6352e7]" @change="mode = 'append'">
              Add to them
            </label>
          </div>
        </template>
      </div>

      <div class="flex justify-end gap-3 p-4 border-t border-line">
        <button class="btn ghost" @click="emit('close')">Cancel</button>
        <button class="btn" :disabled="!options.length" @click="apply">
          Import {{ options.length || '' }}
        </button>
      </div>
    </div>
  </div>
  </Teleport>
</template>

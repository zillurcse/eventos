<script setup lang="ts">
import type { Block, BlockType } from '~/composables/useEmailBlocks'
import { createBlock, PALETTE } from '~/composables/useEmailBlocks'
import type { VarGroup } from '~/components/mail/MailVariableMenu.vue'

const props = defineProps<{ block: Block, nested?: boolean }>()

interface BuilderApi {
  selectedId: Ref<string | null>
  select: (id: string | null) => void
  remove: (id: string) => void
  duplicate: (id: string) => void
  move: (id: string, dir: number) => void
  varGroups: Ref<VarGroup[]>
}
const builder = inject<BuilderApi>('emailBuilder')!

const selected = computed(() => builder.selectedId.value === props.block.id)
const style = computed(() => props.block.style || {})

// ── outer cell style (padding + background) mapped from block.style ──────────
const cellStyle = computed(() => {
  const s = style.value
  return {
    paddingTop: (s.paddingTop ?? 16) + 'px',
    paddingBottom: (s.paddingBottom ?? 16) + 'px',
    paddingLeft: (s.paddingLeft ?? (props.nested ? 0 : 24)) + 'px',
    paddingRight: (s.paddingRight ?? (props.nested ? 0 : 24)) + 'px',
    backgroundColor: s.backgroundColor || 'transparent',
  }
})

function textStyle(extra: Record<string, any> = {}) {
  const s = style.value
  return {
    textAlign: s.align || 'left',
    color: s.color || '#334155',
    fontSize: (s.fontSize || 15) + 'px',
    fontWeight: s.fontWeight || '400',
    lineHeight: s.lineHeight || '1.6',
    margin: 0,
    ...extra,
  }
}

// ── inline rich-text editing (heading + text) ───────────────────────────────
const editable = ref<HTMLElement | null>(null)
let savedRange: Range | null = null

function syncFromDom() {
  if (!editable.value) return
  if (props.block.type === 'heading') props.block.text = editable.value.innerText
  else props.block.html = editable.value.innerHTML
}
function saveRange() {
  const sel = window.getSelection()
  if (sel && sel.rangeCount && editable.value?.contains(sel.anchorNode)) savedRange = sel.getRangeAt(0).cloneRange()
}
function exec(cmd: string, val?: string) {
  editable.value?.focus()
  if (savedRange) { const sel = window.getSelection(); sel?.removeAllRanges(); sel?.addRange(savedRange) }
  document.execCommand(cmd, false, val)
  syncFromDom()
}
function makeLink() {
  const url = prompt('Link URL')
  if (url) exec('createLink', url)
}
function insertVariable(token: string) {
  editable.value?.focus()
  if (savedRange) { const sel = window.getSelection(); sel?.removeAllRanges(); sel?.addRange(savedRange) }
  document.execCommand('insertText', false, `{{ ${token} }}`)
  syncFromDom()
}
// keep DOM in sync when the model changes externally (inspector / not focused)
watch(() => [props.block.html, props.block.text], () => {
  const el = editable.value
  if (!el || document.activeElement === el) return
  const want = props.block.type === 'heading' ? (props.block.text || '') : (props.block.html || '')
  const have = props.block.type === 'heading' ? el.innerText : el.innerHTML
  if (want !== have) { if (props.block.type === 'heading') el.innerText = want; else el.innerHTML = want }
})
onMounted(() => {
  const el = editable.value
  if (!el) return
  if (props.block.type === 'heading') el.innerText = props.block.text || ''
  else el.innerHTML = props.block.html || ''
})

// ── column child add menu ───────────────────────────────────────────────────
const COLUMN_TYPES: BlockType[] = ['heading', 'text', 'button', 'image', 'divider', 'spacer']
const addMenuFor = ref<number | null>(null)
function addToColumn(colIndex: number, type: BlockType) {
  const col = props.block.columns?.[colIndex]
  if (!col) return
  const b = createBlock(type)
  col.push(b)
  addMenuFor.value = null
  builder.select(b.id)
}
const paletteFor = (types: BlockType[]) => PALETTE.filter(p => types.includes(p.type))
</script>

<template>
  <div
    class="relative group/blk transition-shadow"
    :class="selected ? 'outline outline-2 outline-[#6352e7]' : 'hover:outline hover:outline-1 hover:outline-[#c7c2f5]'"
    @click.stop="builder.select(block.id)"
  >
    <!-- floating toolbar -->
    <div
      v-if="selected"
      class="absolute -top-3 right-2 z-30 flex items-center gap-0.5 rounded-lg bg-[#1f2430] text-white px-1 py-0.5 shadow-lg"
      @click.stop
    >
      <button class="ea-tb" title="Move up" @click="builder.move(block.id, -1)">↑</button>
      <button class="ea-tb" title="Move down" @click="builder.move(block.id, 1)">↓</button>
      <button class="ea-tb" title="Duplicate" @click="builder.duplicate(block.id)">⧉</button>
      <button class="ea-tb text-[#ff8585]" title="Delete" @click="builder.remove(block.id)">🗑</button>
    </div>

    <!-- rich-text mini toolbar -->
    <div
      v-if="selected && (block.type === 'text' || block.type === 'heading')"
      class="absolute -top-3 left-2 z-30 flex items-center gap-0.5 rounded-lg bg-white border border-line px-1 py-0.5 shadow-lg"
      @click.stop
    >
      <button v-if="block.type === 'text'" class="ea-fmt font-bold" title="Bold" @mousedown.prevent="exec('bold')">B</button>
      <button v-if="block.type === 'text'" class="ea-fmt italic" title="Italic" @mousedown.prevent="exec('italic')">I</button>
      <button v-if="block.type === 'text'" class="ea-fmt underline" title="Underline" @mousedown.prevent="exec('underline')">U</button>
      <button v-if="block.type === 'text'" class="ea-fmt" title="Link" @mousedown.prevent="makeLink">🔗</button>
      <MailVariableMenu :groups="builder.varGroups.value" compact @insert="insertVariable" />
    </div>

    <div :style="cellStyle">
      <!-- heading -->
      <component
        :is="'h' + (block.level || 1)"
        v-if="block.type === 'heading'"
        ref="editable"
        contenteditable
        class="outline-none whitespace-pre-wrap"
        :style="textStyle({ fontWeight: style.fontWeight || '700', color: style.color || '#0f172a', fontSize: (style.fontSize || 28) + 'px' })"
        @input="syncFromDom"
        @keyup="saveRange"
        @mouseup="saveRange"
        @blur="saveRange"
      />

      <!-- text -->
      <div
        v-else-if="block.type === 'text'"
        ref="editable"
        contenteditable
        class="outline-none"
        :style="textStyle()"
        @input="syncFromDom"
        @keyup="saveRange"
        @mouseup="saveRange"
        @blur="saveRange"
      />

      <!-- button -->
      <div v-else-if="block.type === 'button'" :style="{ textAlign: style.align || 'left' }">
        <span :style="{
          display: style.fullWidth ? 'block' : 'inline-block',
          textAlign: 'center',
          padding: (style.paddingY || 13) + 'px ' + (style.paddingX || 26) + 'px',
          background: style.backgroundColor || '#6352e7',
          color: style.color || '#fff',
          borderRadius: (style.borderRadius || 8) + 'px',
          fontSize: (style.fontSize || 15) + 'px',
          fontWeight: 600,
        }">{{ block.text || 'Button' }}</span>
      </div>

      <!-- image -->
      <div v-else-if="block.type === 'image'" :style="{ textAlign: style.align || 'center' }">
        <img
          v-if="block.src"
          :src="block.src"
          :alt="block.alt"
          :style="{ width: (style.width || 100) + '%', maxWidth: '100%', borderRadius: (style.borderRadius || 0) + 'px', display: 'inline-block' }"
        >
        <div v-else class="border-2 border-dashed border-line rounded-lg py-9 text-center text-[#8b93a7] text-[.85rem]">
          🖼 No image — select and add a URL or upload
        </div>
      </div>

      <!-- divider -->
      <hr v-else-if="block.type === 'divider'" :style="{ border: 0, borderTop: (style.height || 1) + 'px solid ' + (style.color || '#e2e8f0'), width: (style.width || 100) + '%', margin: '0 auto' }">

      <!-- spacer -->
      <div v-else-if="block.type === 'spacer'" :style="{ height: (style.height || 24) + 'px' }" class="bg-[repeating-linear-gradient(45deg,#f1f1f6,#f1f1f6_6px,#fafafe_6px,#fafafe_12px)] rounded" />

      <!-- social -->
      <div v-else-if="block.type === 'social'" :style="{ textAlign: style.align || 'center' }">
        <span v-for="(it, i) in block.items" :key="i" class="inline-block mx-1.5 text-[.82rem]" :style="{ color: style.color || '#64748b' }">{{ it.network }}</span>
      </div>

      <!-- html -->
      <div v-else-if="block.type === 'html'" class="text-[.78rem] font-mono text-[#8b93a7] whitespace-pre-wrap break-all">{{ block.html }}</div>

      <!-- columns -->
      <div v-else-if="block.type === 'columns'" class="flex" :style="{ gap: (style.gap || 16) + 'px' }">
        <div
          v-for="(col, ci) in block.columns"
          :key="ci"
          class="flex-1 min-w-0 rounded-lg border border-dashed border-[#d9d6f3] bg-[#fbfbff]"
        >
          <MailCanvasBlock v-for="child in col" :key="child.id" :block="child" nested />
          <div class="relative p-2 text-center">
            <button class="text-[.72rem] text-[#6352e7] font-semibold border border-line rounded-md px-2 py-1 bg-white cursor-pointer hover:bg-[#f5f3ff]" @click.stop="addMenuFor = addMenuFor === ci ? null : ci">+ Add</button>
            <div v-if="addMenuFor === ci" class="absolute z-30 left-1/2 -translate-x-1/2 mt-1 grid grid-cols-3 gap-1 bg-white border border-line rounded-lg p-1.5 shadow-lg w-[180px]" @click.stop>
              <button v-for="p in paletteFor(COLUMN_TYPES)" :key="p.type" class="flex flex-col items-center gap-0.5 p-1.5 rounded hover:bg-[#f5f3ff] cursor-pointer" @click="addToColumn(ci, p.type)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6352e7" stroke-width="2" stroke-linecap="round"><path v-for="(d, i) in p.icon.split(' M').map((s, idx) => idx ? 'M' + s : s)" :key="i" :d="d" /></svg>
                <span class="text-[.62rem] text-[#5f6b7a]">{{ p.label }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ea-tb { width: 26px; height: 24px; display: grid; place-items: center; background: transparent; border: 0; color: #fff; cursor: pointer; border-radius: 5px; font-size: 13px; }
.ea-tb:hover { background: rgba(255,255,255,.16); }
.ea-fmt { width: 26px; height: 24px; display: grid; place-items: center; background: transparent; border: 0; color: #1f2430; cursor: pointer; border-radius: 5px; font-size: 13px; }
.ea-fmt:hover { background: #f0eefe; }
</style>

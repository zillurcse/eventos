<script setup lang="ts">
import type { Block, BlockType } from '../../composables/useEmailBlocks'
import { createBlock, PALETTE } from '../../composables/useEmailBlocks'
import type { VarGroup } from './MailVariableMenu.vue'

const props = defineProps<{ block: Block, nested?: boolean }>()

interface BuilderApi {
  selectedId: Ref<string | null>
  dragId: Ref<string | null>
  select: (id: string | null) => void
  remove: (id: string) => void
  duplicate: (id: string) => void
  move: (id: string, dir: number) => void
  varGroups: Ref<VarGroup[]>
  startBlockDrag: (id: string) => void
  endBlockDrag: () => void
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

// ── social SVG icons ────────────────────────────────────────────────────────
const SOCIAL_ICONS: Record<string, string> = {
  twitter: '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.738l7.73-8.835L1.254 2.25H8.08l4.259 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>',
  facebook: '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>',
  linkedin: '<path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>',
  instagram: '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 1 0 0 12.324 6.162 6.162 0 0 0 0-12.324zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm6.406-11.845a1.44 1.44 0 1 0 0 2.881 1.44 1.44 0 0 0 0-2.881z"/>',
  youtube: '<path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/>',
  tiktok: '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>',
  website: '<path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm0 2c.95 0 2.1.76 3.07 2.71.38.78.7 1.7.94 2.79H8c.23-1.09.55-2 .93-2.79C9.9 4.76 11.05 4 12 4zM4.2 10h3.45c-.1.64-.15 1.31-.15 2s.05 1.36.15 2H4.2A8 8 0 0 1 4 12a8 8 0 0 1 .2-2zm2.32 6h2.8c.28 1.13.65 2.11 1.1 2.93A8.03 8.03 0 0 1 6.52 16zm2.8-8H6.52a8.03 8.03 0 0 1 3.9-2.93A13.04 13.04 0 0 0 9.32 8zm3.13 10.5c-.95 0-2.1-.76-3.07-2.71-.35-.71-.65-1.55-.88-2.79h7.98c-.23 1.24-.53 2.08-.88 2.79-.97 1.95-2.12 2.71-3.15 2.71zm3.9-4.5h-7.7c-.1-.64-.15-1.31-.15-2s.05-1.36.15-2h7.7c.1.64.15 1.31.15 2s-.05 1.36-.15 2zm.26 4.93A13.04 13.04 0 0 0 17.49 16h-2.81c-.28 1.13-.65 2.11-1.1 2.93zm1.97-4.93c.1-.64.15-1.31.15-2s-.05-1.36-.15-2h3.45c.13.64.2 1.31.2 2a8 8 0 0 1-.2 2h-3.45zm-.83-6h-2.81A13.04 13.04 0 0 0 13.68 5.07 8.03 8.03 0 0 1 17.48 8z"/>',
}

function socialIcon(network: string): string {
  return SOCIAL_ICONS[network] ?? SOCIAL_ICONS.website!
}

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
    <!-- drag handle (visible on hover) -->
    <div
      class="absolute top-1/2 -translate-y-1/2 left-1.5 z-30 opacity-0 group-hover/blk:opacity-100 transition-opacity cursor-grab active:cursor-grabbing touch-none"
      draggable="true"
      title="Drag to reorder"
      @dragstart.stop="builder.startBlockDrag(block.id)"
      @dragend.stop="builder.endBlockDrag()"
      @click.stop
    >
      <svg width="10" height="16" viewBox="0 0 10 16" fill="#6352e7" opacity="0.7">
        <circle cx="3" cy="2.5" r="1.5"/><circle cx="7" cy="2.5" r="1.5"/>
        <circle cx="3" cy="8" r="1.5"/><circle cx="7" cy="8" r="1.5"/>
        <circle cx="3" cy="13.5" r="1.5"/><circle cx="7" cy="13.5" r="1.5"/>
      </svg>
    </div>

    <!-- floating toolbar -->
    <div
      v-if="selected"
      class="absolute -top-3 right-2 z-30 flex items-center gap-0.5 rounded-lg bg-[#1f2430] text-white px-1 py-0.5 shadow-lg"
      @click.stop
    >
      <div
        class="ea-tb cursor-grab active:cursor-grabbing touch-none"
        draggable="true"
        title="Drag to reorder"
        @dragstart.stop="builder.startBlockDrag(block.id)"
        @dragend.stop="builder.endBlockDrag()"
      >⠿</div>
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
          No image. Add a URL or upload one.
        </div>
      </div>

      <!-- divider -->
      <hr v-else-if="block.type === 'divider'" :style="{ border: 0, borderTop: (style.height || 1) + 'px solid ' + (style.color || '#e2e8f0'), width: (style.width || 100) + '%', margin: '0 auto' }">

      <!-- spacer -->
      <div v-else-if="block.type === 'spacer'" :style="{ height: (style.height || 24) + 'px' }" class="bg-[repeating-linear-gradient(45deg,#f1f1f6,#f1f1f6_6px,#fafafe_6px,#fafafe_12px)] rounded" />

      <!-- social -->
      <div v-else-if="block.type === 'social'" :style="{ textAlign: style.align || 'center' }">
        <a v-for="(it, i) in block.items" :key="i" :href="it.url" target="_blank" class="inline-block mx-1.5" :title="it.network">
          <svg :width="style.iconSize || 28" :height="style.iconSize || 28" viewBox="0 0 24 24" :fill="style.color || '#64748b'" v-html="socialIcon(it.network)" />
        </a>
      </div>

      <!-- html — rendered live so designers see the actual output -->
      <div v-else-if="block.type === 'html'" class="email-html-block" v-html="block.html || '<p style=\'color:#94a3b8;font-size:13px;text-align:center\'>Custom HTML block. Edit it in the inspector.</p>'" />

      <!-- logo -->
      <div v-else-if="block.type === 'logo'" :style="{ textAlign: style.align || 'center', backgroundColor: style.backgroundColor || 'transparent' }">
        <img
          v-if="block.src"
          :src="block.src"
          :alt="block.alt || 'Logo'"
          :style="{ width: (style.width || 160) + 'px', maxWidth: '100%', display: 'inline-block' }"
        >
        <div v-else class="inline-flex items-center gap-2 text-[.8rem] border-2 border-dashed border-line rounded-lg px-4 py-2.5 text-[#8b93a7]">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#8b93a7" stroke-width="1.6"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
          Upload a logo or paste a URL
        </div>
      </div>

      <!-- video -->
      <div v-else-if="block.type === 'video'" :style="{ textAlign: style.align || 'center' }">
        <div
          class="relative inline-block overflow-hidden cursor-pointer"
          :style="{ borderRadius: (style.borderRadius || 8) + 'px', maxWidth: '100%', width: '100%' }"
        >
          <img
            v-if="block.src"
            :src="block.src"
            alt="Video thumbnail"
            class="w-full block"
            :style="{ borderRadius: (style.borderRadius || 8) + 'px' }"
          >
          <div v-else class="border-2 border-dashed border-line rounded-xl py-9 text-center text-[#8b93a7] text-[.85rem]">
            🎬 Add a thumbnail image URL in the inspector
          </div>
          <!-- play overlay -->
          <div class="absolute inset-0 flex items-center justify-center" :style="{ background: 'rgba(0,0,0,0.32)' }">
            <div class="w-14 h-14 rounded-full bg-white/90 flex items-center justify-center shadow-lg">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="#1f2430"><path d="M5 3l14 9-14 9V3z"/></svg>
            </div>
          </div>
        </div>
      </div>

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

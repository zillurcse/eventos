<script setup lang="ts">
import type { Block, BlockType, EmailSettings, TemplatePreset, AuditIssue } from '../../composables/useEmailBlocks'
import {
  createBlock, cloneBlock, findContext, walkBlocks,
  defaultSettings, starterBlocks, auditDesign, PALETTE, CATEGORIES,
} from '../../composables/useEmailBlocks'
import type { VarGroup } from './MailVariableMenu.vue'

interface TemplateDto {
  id: string
  name: string
  subject?: string | null
  preheader?: string | null
  category?: string | null
  from_name?: string | null
  from_email?: string | null
  reply_to?: string | null
  status?: string
  blocks?: Block[]
  settings?: Partial<EmailSettings>
}

const props = defineProps<{ eventId: string, template: TemplateDto | null }>()
const emit = defineEmits<{ (e: 'close'): void, (e: 'saved', t: TemplateDto): void }>()

const api = useApi()

// ── document state ───────────────────────────────────────────────────────
const name = ref(props.template?.name || 'Untitled email')
const subject = ref(props.template?.subject || '')
const preheader = ref(props.template?.preheader || '')
const category = ref(props.template?.category || 'custom')
const fromName = ref(props.template?.from_name || '')
const fromEmail = ref(props.template?.from_email || '')
const replyTo = ref(props.template?.reply_to || '')
const uuid = ref(props.template?.id || '')
const blocks = reactive<Block[]>(
  props.template?.blocks?.length ? JSON.parse(JSON.stringify(props.template.blocks)) : starterBlocks(),
)
const settings = reactive<EmailSettings>({ ...defaultSettings(), ...(props.template?.settings || {}) })

const selectedId = ref<string | null>(null)
const varGroups = ref<VarGroup[]>([])
const mode = ref<'edit' | 'preview'>('edit')
const device = ref<'desktop' | 'mobile'>('desktop')
const saving = ref(false)
const dirty = ref(false)
const savedAt = ref<Date | null>(null)
const saveError = ref('')
const showGallery = ref(!props.template)
const showHistory = ref(false)
const showSource = ref(false)
const showAudit = ref(false)

// ── undo / redo ──────────────────────────────────────────────────────────────
const history = ref<string[]>([])
const historyIndex = ref(-1)
const historyPausing = ref(false)

function snapshot() {
  if (historyPausing.value) return
  const snap = JSON.stringify({ blocks: JSON.parse(JSON.stringify(blocks)), settings: JSON.parse(JSON.stringify(settings)) })
  if (history.value[historyIndex.value] === snap) return
  // drop redo tail
  history.value = history.value.slice(0, historyIndex.value + 1)
  history.value.push(snap)
  if (history.value.length > 60) history.value.shift()
  historyIndex.value = history.value.length - 1
}
function applySnapshot(snap: string) {
  const { blocks: b, settings: s } = JSON.parse(snap)
  historyPausing.value = true
  blocks.splice(0, blocks.length, ...b)
  Object.assign(settings, s)
  historyPausing.value = false
}
function undo() {
  if (historyIndex.value <= 0) return
  historyIndex.value--
  applySnapshot(history.value[historyIndex.value]!)
  dirty.value = true
}
function redo() {
  if (historyIndex.value >= history.value.length - 1) return
  historyIndex.value++
  applySnapshot(history.value[historyIndex.value]!)
  dirty.value = true
}
const canUndo = computed(() => historyIndex.value > 0)
const canRedo = computed(() => historyIndex.value < history.value.length - 1)

// ── selected block lookup ────────────────────────────────────────────────
const selectedBlock = computed<Block | null>(() => {
  if (!selectedId.value) return null
  let found: Block | null = null
  walkBlocks(blocks, b => { if (b.id === selectedId.value) found = b })
  return found
})

// ── pre-send audit ───────────────────────────────────────────────────────
const issues = computed<AuditIssue[]>(() =>
  auditDesign(blocks, { subject: subject.value, preheader: preheader.value }),
)
const errorCount = computed(() => issues.value.filter(i => i.severity === 'error').length)

function focusIssue(issue: AuditIssue) {
  if (issue.blockId) {
    selectedId.value = issue.blockId
    mode.value = 'edit'
    showAudit.value = false
  }
}

// ── drag state ───────────────────────────────────────────────────────────
const dragId = ref<string | null>(null)
const dragType = ref<BlockType | null>(null)
const dropIndex = ref<number | null>(null)
const canvasEl = ref<HTMLElement | null>(null)

// ── builder API (provided to nested canvas blocks) ───────────────────────
provide('emailBuilder', {
  selectedId,
  varGroups,
  dragId,
  select: (id: string | null) => { selectedId.value = id },
  remove: (id: string) => {
    const ctx = findContext(blocks, id)
    if (ctx) ctx.arr.splice(ctx.index, 1)
    if (selectedId.value === id) selectedId.value = null
  },
  duplicate: (id: string) => {
    const ctx = findContext(blocks, id)
    if (!ctx) return
    const copy = cloneBlock(ctx.arr[ctx.index]!)
    ctx.arr.splice(ctx.index + 1, 0, copy)
    selectedId.value = copy.id
  },
  move: (id: string, dir: number) => {
    const ctx = findContext(blocks, id)
    if (!ctx) return
    const j = ctx.index + dir
    if (j < 0 || j >= ctx.arr.length) return
    const [b] = ctx.arr.splice(ctx.index, 1)
    ctx.arr.splice(j, 0, b!)
  },
  startBlockDrag: (id: string) => { dragId.value = id },
  endBlockDrag: () => { dragId.value = null; dropIndex.value = null },
})

// mark dirty + snapshot + refresh preview on any document change
let snapTimer: ReturnType<typeof setTimeout> | null = null
watch([blocks, settings], () => {
  dirty.value = true
  if (snapTimer) clearTimeout(snapTimer)
  snapTimer = setTimeout(snapshot, 400)
  schedulePreview()
  scheduleAutosave()
}, { deep: true })

watch([name, subject, preheader, category, fromName, fromEmail, replyTo], () => {
  dirty.value = true
  schedulePreview()
  scheduleAutosave()
})

// ── autosave ─────────────────────────────────────────────────────────────
/**
 * Only runs once the template exists — a brand-new draft is not persisted
 * until the author saves it deliberately, so opening the editor and closing it
 * again doesn't litter the gallery with empty templates.
 */
const autosaveEnabled = computed(() => !!uuid.value)
let autosaveTimer: ReturnType<typeof setTimeout> | null = null

function scheduleAutosave() {
  if (!autosaveEnabled.value) return
  if (autosaveTimer) clearTimeout(autosaveTimer)
  autosaveTimer = setTimeout(() => { if (dirty.value && !saving.value) save() }, 2500)
}

const saveLabel = computed(() => {
  if (saving.value) return 'Saving…'
  if (saveError.value) return saveError.value
  if (dirty.value) return autosaveEnabled.value ? 'Unsaved' : 'Unsaved — save once to enable autosave'
  if (savedAt.value) return `Saved ${savedAt.value.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })}`
  return ''
})

// ── palette add / drag ───────────────────────────────────────────────────
function addBlock(type: BlockType, index?: number) {
  const b = createBlock(type)
  if (index === undefined) blocks.push(b)
  else blocks.splice(index, 0, b)
  selectedId.value = b.id
}
/** Called from the canvas @dragover — computes nearest slot from mouse Y */
function onCanvasDragOver(e: DragEvent) {
  if (!dragId.value && !dragType.value) return
  if (!canvasEl.value) return
  const wrappers = Array.from(canvasEl.value.querySelectorAll<HTMLElement>('[data-bidx]'))
  if (!wrappers.length) { dropIndex.value = 0; return }
  let slot = blocks.length
  for (const el of wrappers) {
    const rect = el.getBoundingClientRect()
    if (e.clientY < rect.top + rect.height / 2) {
      slot = parseInt(el.dataset.bidx!)
      break
    }
  }
  // skip the slot that would leave the dragged block in the same position
  if (dragId.value) {
    const from = blocks.findIndex(b => b.id === dragId.value)
    if (slot === from || slot === from + 1) { dropIndex.value = null; return }
  }
  dropIndex.value = slot
}

function commitDrop() {
  const index = dropIndex.value
  dropIndex.value = null
  if (index === null) { dragId.value = null; dragType.value = null; return }
  if (dragId.value) {
    const fromIndex = blocks.findIndex(b => b.id === dragId.value)
    if (fromIndex !== -1) {
      const [b] = blocks.splice(fromIndex, 1)
      const toIndex = fromIndex < index ? index - 1 : index
      blocks.splice(Math.max(0, toIndex), 0, b!)
    }
    dragId.value = null
  } else if (dragType.value) {
    addBlock(dragType.value, index)
    dragType.value = null
  }
}
function iconPaths(icon: string) {
  return icon.split(' M').map((s, i) => (i ? 'M' + s : s))
}
// literals kept in script so "}}" never reaches Vue's mustache parser
const VAR_EXAMPLE = '{{ variables }}'
const OPEN_BRACES = '{{'
const SUBJECT_PLACEHOLDER = "e.g. You're invited, {{ contact.first_name }}"
const PREHEADER_PLACEHOLDER = 'Shown after the subject in the inbox list'

// ── server-rendered preview ──────────────────────────────────────────────
const previewHtml = ref('')
const previewLoading = ref(false)
let previewTimer: ReturnType<typeof setTimeout> | null = null
let previewToken = 0

/**
 * Debounced so a burst of typing produces one render, and sequenced with a
 * token so a slow earlier response can't overwrite a newer one.
 */
function schedulePreview() {
  if (previewTimer) clearTimeout(previewTimer)
  previewTimer = setTimeout(refreshPreview, 500)
}

async function refreshPreview() {
  const token = ++previewToken
  previewLoading.value = true
  try {
    const res = await api<{ html: string }>('/email-templates/preview-draft', {
      method: 'POST',
      body: { subject: subject.value, preheader: preheader.value, blocks, settings },
    })
    if (token === previewToken) previewHtml.value = res.html
  } catch {
    /* preview is advisory — a failure shouldn't interrupt editing */
  } finally {
    if (token === previewToken) previewLoading.value = false
  }
}

// ── persistence ──────────────────────────────────────────────────────────
function payload(status?: string) {
  return {
    name: name.value.trim() || 'Untitled email',
    subject: subject.value || null,
    preheader: preheader.value || null,
    category: category.value || 'custom',
    from_name: fromName.value || null,
    from_email: fromEmail.value || null,
    reply_to: replyTo.value || null,
    event: props.eventId,
    blocks,
    settings,
    ...(status ? { status } : {}),
  }
}
async function save(status?: string): Promise<TemplateDto | null> {
  if (saving.value) return null
  saving.value = true
  saveError.value = ''
  try {
    const res = uuid.value
      ? await api<{ data: TemplateDto }>(`/email-templates/${uuid.value}`, { method: 'PUT', body: payload(status) })
      : await api<{ data: TemplateDto }>('/email-templates', { method: 'POST', body: payload(status) })
    uuid.value = res.data.id
    dirty.value = false
    savedAt.value = new Date()
    emit('saved', res.data)
    return res.data
  } catch (e: any) {
    saveError.value = e?.data?.message || 'Save failed'
    return null
  } finally {
    saving.value = false
  }
}

/** Pull the server's copy back in after a version restore. */
async function reloadFromServer() {
  if (!uuid.value) return
  const { data } = await api<{ data: TemplateDto }>(`/email-templates/${uuid.value}`)
  name.value = data.name
  subject.value = data.subject || ''
  preheader.value = data.preheader || ''
  category.value = data.category || 'custom'
  historyPausing.value = true
  blocks.splice(0, blocks.length, ...JSON.parse(JSON.stringify(data.blocks || [])))
  Object.assign(settings, { ...defaultSettings(), ...(data.settings || {}) })
  historyPausing.value = false
  dirty.value = false
  showHistory.value = false
  nextTick(() => { snapshot(); refreshPreview() })
}

const testEmail = ref('')
const testOpen = ref(false)
const testSending = ref(false)
const testMsg = ref('')
async function sendTest() {
  if (!testEmail.value || testSending.value) return
  testSending.value = true
  testMsg.value = ''
  try {
    if (!uuid.value || dirty.value) await save()
    await api(`/email-templates/${uuid.value}/send-test`, { method: 'POST', body: { to: testEmail.value } })
    testMsg.value = `Sent to ${testEmail.value} ✓`
  } catch (e: any) {
    testMsg.value = e?.data?.message || 'Failed to send'
  } finally {
    testSending.value = false
  }
}

function applyGalleryPreset(preset: TemplatePreset) {
  const newBlocks = preset.blocks().map(b => JSON.parse(JSON.stringify(b)))
  const newSettings = { ...defaultSettings(), ...preset.settings() }
  blocks.splice(0, blocks.length, ...newBlocks)
  Object.assign(settings, newSettings)
  showGallery.value = false
  dirty.value = false
  nextTick(() => { snapshot(); refreshPreview() })
}

function onKeydown(e: KeyboardEvent) {
  const ctrl = e.ctrlKey || e.metaKey
  if (ctrl && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo() }
  if (ctrl && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo() }
  if (ctrl && e.key === 's') { e.preventDefault(); save() }
}

/** Don't let a browser tab close swallow unsaved work. */
function onBeforeUnload(e: BeforeUnloadEvent) {
  if (dirty.value) e.preventDefault()
}

function requestClose() {
  if (dirty.value && !confirm('You have unsaved changes. Close the editor anyway?')) return
  emit('close')
}

/**
 * The editor owns a route, so leaving is not only the Back button in its own
 * topbar — browser Back/Forward and any in-app link would otherwise discard
 * unsaved work silently. Does not fire for the `/new` → uuid rewrite, which is
 * a param change on the same route (and happens with nothing pending anyway).
 */
onBeforeRouteLeave(() => {
  if (!dirty.value) return true
  return confirm('You have unsaved changes. Leave the editor anyway?')
})

onMounted(async () => {
  try { varGroups.value = (await api<{ data: VarGroup[] }>('/email-variables')).data } catch { /* non-fatal */ }
  snapshot()
  refreshPreview()
  window.addEventListener('keydown', onKeydown)
  window.addEventListener('beforeunload', onBeforeUnload)
})
onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('beforeunload', onBeforeUnload)
  if (autosaveTimer) clearTimeout(autosaveTimer)
  if (previewTimer) clearTimeout(previewTimer)
  if (snapTimer) clearTimeout(snapTimer)
})
</script>

<template>
  <div class="fixed inset-0 z-[150] bg-[#eef0f4] flex flex-col">
    <!-- ───────── Topbar ───────── -->
    <header class="h-[58px] shrink-0 bg-white border-b border-line flex items-center gap-3 px-4">
      <button class="w-9 h-9 rounded-lg border border-line bg-white grid place-items-center cursor-pointer hover:bg-[#f5f5fa]" title="Back" @click="requestClose">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5f6b7a" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6" /></svg>
      </button>
      <input v-model="name" class="m-0 w-[220px] font-semibold border-transparent hover:border-line focus:border-[#6352e7] bg-transparent" placeholder="Template name">
      <span
        v-if="saveLabel"
        class="text-[.72rem] px-2 py-0.5 rounded-full whitespace-nowrap"
        :class="saveError ? 'text-[#b91c1c] bg-[#fee2e2]' : dirty ? 'text-[#b45309] bg-[#fef3c7]' : 'text-[#15803d] bg-[#dcfce7]'"
      >{{ saveLabel }}</span>

      <div class="ml-auto flex items-center gap-2">
        <!-- pre-send audit -->
        <button
          class="relative flex items-center gap-1.5 px-2.5 h-8 rounded-lg border border-line bg-white cursor-pointer text-[.8rem] hover:bg-[#f5f5fa]"
          :class="errorCount ? '!border-[#fca5a5] !text-[#b91c1c]' : issues.length ? '!border-[#fcd34d] !text-[#92400e]' : 'text-[#15803d]'"
          :title="issues.length ? `${issues.length} thing${issues.length > 1 ? 's' : ''} to check before sending` : 'No issues found'"
          @click="showAudit = !showAudit"
        >
          <span>{{ issues.length ? '⚠' : '✓' }}</span>
          <span>{{ issues.length || 'Ready' }}</span>
        </button>

        <!-- undo / redo -->
        <div class="flex border border-line rounded-lg overflow-hidden">
          <button class="w-8 h-8 grid place-items-center cursor-pointer bg-white hover:bg-[#f5f5fa] disabled:opacity-30 disabled:cursor-not-allowed border-r border-line" title="Undo (Ctrl+Z)" :disabled="!canUndo" @click="undo">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7h10a6 6 0 0 1 0 12H7"/><path d="M7 3l-4 4 4 4"/></svg>
          </button>
          <button class="w-8 h-8 grid place-items-center cursor-pointer bg-white hover:bg-[#f5f5fa] disabled:opacity-30 disabled:cursor-not-allowed" title="Redo (Ctrl+Y)" :disabled="!canRedo" @click="redo">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 7H11a6 6 0 0 0 0 12h6"/><path d="M17 3l4 4-4 4"/></svg>
          </button>
        </div>

        <div class="flex bg-[#f1f1f6] rounded-lg p-0.5">
          <button class="px-3 py-1.5 rounded-md text-[.82rem] cursor-pointer" :class="mode === 'edit' ? 'bg-white shadow-sm font-semibold' : 'text-[#5f6b7a]'" @click="mode = 'edit'">Edit</button>
          <button class="px-3 py-1.5 rounded-md text-[.82rem] cursor-pointer" :class="mode === 'preview' ? 'bg-white shadow-sm font-semibold' : 'text-[#5f6b7a]'" @click="mode = 'preview'">Preview</button>
        </div>

        <button class="btn ghost sm" :disabled="!uuid" title="Version history" @click="showHistory = true">History</button>
        <button class="btn ghost sm" title="View compiled HTML" @click="showSource = true">HTML</button>
        <button class="btn ghost sm" @click="testOpen = true">Send test</button>
        <button class="btn sm" :disabled="saving" @click="save()">{{ saving ? 'Saving…' : 'Save' }}</button>
        <button class="btn sm" style="background:#16a34a" :disabled="saving" title="Save & mark ready to use" @click="save('published')">Save &amp; publish</button>
      </div>
    </header>

    <!-- ───────── Audit panel ───────── -->
    <div v-if="showAudit" class="shrink-0 bg-white border-b border-line px-4 py-3 max-h-[180px] overflow-y-auto">
      <div class="flex items-center gap-2 mb-2">
        <h4 class="m-0 text-[.8rem] font-bold uppercase tracking-wider text-[#8b93a7]">Before you send</h4>
        <button class="ml-auto text-[#8b93a7] bg-transparent border-0 cursor-pointer text-[.8rem]" @click="showAudit = false">Close</button>
      </div>
      <p v-if="!issues.length" class="m-0 text-[.85rem] text-[#15803d]">✓ No issues found — alt text, links and subject all look good.</p>
      <ul v-else class="list-none m-0 p-0 flex flex-col gap-1">
        <li
          v-for="(issue, i) in issues"
          :key="i"
          class="flex items-start gap-2 text-[.83rem]"
          :class="issue.blockId ? 'cursor-pointer hover:underline' : ''"
          @click="focusIssue(issue)"
        >
          <span :class="issue.severity === 'error' ? 'text-[#b91c1c]' : 'text-[#b45309]'">{{ issue.severity === 'error' ? '●' : '○' }}</span>
          <span>{{ issue.message }}</span>
        </li>
      </ul>
    </div>

    <div class="flex-1 flex min-h-0">
      <!-- ───────── Left palette ───────── -->
      <aside v-if="mode === 'edit'" class="w-[150px] shrink-0 bg-white border-r border-line p-3 overflow-y-auto">
        <h4 class="text-[.68rem] uppercase tracking-wider text-[#8b93a7] font-bold mb-2">Blocks</h4>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="p in PALETTE"
            :key="p.type"
            draggable="true"
            class="flex flex-col items-center gap-1 py-2.5 rounded-lg border border-line bg-[#fbfbfe] cursor-grab hover:border-[#6352e7] hover:bg-[#f5f3ff] active:cursor-grabbing"
            @click="addBlock(p.type)"
            @dragstart="dragType = p.type"
            @dragend="dragType = null"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#6352e7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path v-for="(d, i) in iconPaths(p.icon)" :key="i" :d="d" /></svg>
            <span class="text-[.68rem] text-[#5f6b7a]">{{ p.label }}</span>
          </button>
        </div>
        <button class="w-full mt-4 text-[.78rem] text-[#6352e7] font-semibold border border-line rounded-lg py-2 cursor-pointer hover:bg-[#f5f3ff]" @click="selectedId = null">⚙ Email settings</button>
        <button class="w-full mt-2 text-[.78rem] text-[#5f6b7a] font-semibold border border-line rounded-lg py-2 cursor-pointer hover:bg-[#f5f5fa]" @click="showGallery = true">📋 Templates</button>
      </aside>

      <!-- ───────── Canvas ───────── -->
      <main class="flex-1 overflow-y-auto p-6" @click="selectedId = null">
        <!-- preview -->
        <div v-if="mode === 'preview'" class="h-full flex flex-col items-center">
          <div class="flex gap-2 mb-3 items-center">
            <button class="btn ghost sm" :class="device === 'desktop' ? '!border-[#6352e7] !text-[#6352e7]' : ''" @click="device = 'desktop'">🖥 Desktop</button>
            <button class="btn ghost sm" :class="device === 'mobile' ? '!border-[#6352e7] !text-[#6352e7]' : ''" @click="device = 'mobile'">📱 Mobile</button>
            <span v-if="previewLoading" class="text-[#8b93a7] text-[.78rem]">Rendering…</span>
            <span v-else class="text-[#8b93a7] text-[.78rem]">Updates as you edit</span>
          </div>
          <!--
            Sandboxed: an srcdoc iframe would otherwise inherit this origin, and
            the document it renders is built from author-supplied HTML.
          -->
          <iframe
            :srcdoc="previewHtml"
            sandbox="allow-popups allow-popups-to-escape-sandbox"
            title="Email preview"
            class="bg-white rounded-xl shadow-lg border border-line transition-all"
            :style="{ width: device === 'mobile' ? '380px' : '760px', height: '100%', maxWidth: '100%' }"
          />
        </div>

        <!-- edit canvas -->
        <div v-else class="mx-auto" :style="{ maxWidth: settings.contentWidth + 'px' }">
          <div
            ref="canvasEl"
            class="rounded-xl shadow-sm overflow-hidden"
            :style="{ background: settings.contentBackground, fontFamily: settings.fontFamily }"
            @click.stop
            @dragover.prevent="onCanvasDragOver"
            @dragleave.self="dropIndex = null"
            @drop.prevent="commitDrop"
          >
            <!-- drop line at top -->
            <DropLine :show="dropIndex === 0" />

            <template v-for="(b, i) in blocks" :key="b.id">
              <div :data-bidx="i" :class="dragId === b.id ? 'opacity-30 pointer-events-none' : ''">
                <MailCanvasBlock :block="b" />
              </div>
              <!-- drop line after each block -->
              <DropLine :show="dropIndex === i + 1" />
            </template>

            <div v-if="!blocks.length" class="py-16 text-center text-[#8b93a7]">
              Drag a block here or click one on the left to start.
            </div>
          </div>
          <p class="text-center text-[#a8aec0] text-[.74rem] mt-3">Tip: click any element to edit it. Type <span class="font-mono">{{ OPEN_BRACES }}</span> in any text to insert <span class="font-mono">{{ VAR_EXAMPLE }}</span>.</p>
        </div>
      </main>

      <!-- ───────── Right inspector ───────── -->
      <aside v-if="mode === 'edit'" class="w-[300px] shrink-0 bg-white border-l border-line overflow-y-auto">
        <!-- subject / sender (always) -->
        <div class="p-4 border-b border-line bg-[#fafafe]">
          <label class="text-[.76rem] font-semibold text-[#5f6b7a] flex items-center justify-between">Subject line <MailVariableMenu :groups="varGroups" compact @insert="t => subject += `{{ ${t} }}`" /></label>
          <input v-model="subject" class="m-0 mt-1" :placeholder="SUBJECT_PLACEHOLDER">

          <label class="text-[.76rem] font-semibold text-[#5f6b7a] flex items-center justify-between mt-3">Preheader <MailVariableMenu :groups="varGroups" compact @insert="t => preheader += `{{ ${t} }}`" /></label>
          <input v-model="preheader" class="m-0 mt-1" :placeholder="PREHEADER_PLACEHOLDER" maxlength="200">
          <p class="text-[#8b93a7] text-[.7rem] mt-1 mb-0">The grey line after the subject in most inboxes.</p>

          <label class="text-[.76rem] font-semibold text-[#5f6b7a] block mt-3 mb-1">Category</label>
          <select v-model="category" class="m-0">
            <option v-for="c in CATEGORIES" :key="c.key" :value="c.key">{{ c.label }}</option>
          </select>

          <details class="mt-3">
            <summary class="text-[.76rem] font-semibold text-[#5f6b7a] cursor-pointer select-none">Sender details</summary>
            <label class="text-[.72rem] text-[#5f6b7a] block mt-2 mb-0.5">From name</label>
            <input v-model="fromName" class="m-0" placeholder="Northwind Events">
            <label class="text-[.72rem] text-[#5f6b7a] block mt-2 mb-0.5">From email</label>
            <input v-model="fromEmail" class="m-0" placeholder="hello@your-domain.com">
            <label class="text-[.72rem] text-[#5f6b7a] block mt-2 mb-0.5">Reply-to</label>
            <input v-model="replyTo" class="m-0" placeholder="support@your-domain.com">
          </details>
        </div>
        <div class="p-4">
          <MailInspector :block="selectedBlock" :settings="settings" :var-groups="varGroups" />
        </div>
      </aside>
    </div>

    <!-- template gallery -->
    <MailTemplateGallery
      v-if="showGallery"
      @select="applyGalleryPreset"
      @close="showGallery = false"
    />

    <!-- version history -->
    <MailVersionHistory
      v-if="showHistory && uuid"
      :template-id="uuid"
      @restored="reloadFromServer"
      @close="showHistory = false"
    />

    <!-- compiled HTML source -->
    <MailSourceView
      v-if="showSource"
      :html="previewHtml"
      :loading="previewLoading"
      @close="showSource = false"
    />

    <!-- send-test modal -->
    <div v-if="testOpen" class="fixed inset-0 z-[160] bg-black/35 grid place-items-center" @click.self="testOpen = false">
      <div class="bg-white rounded-2xl p-5 w-[380px] max-w-[92vw] shadow-xl">
        <h3 class="m-0 mb-1 text-[1.05rem]">Send a test email</h3>
        <p class="text-[#8b93a7] text-[.82rem] mt-0 mb-3">Variables render with sample data. Saves the template first.</p>
        <p v-if="errorCount" class="text-[#b91c1c] text-[.8rem] mt-0 mb-2">⚠ {{ errorCount }} unresolved issue{{ errorCount > 1 ? 's' : '' }} — check the audit panel first.</p>
        <input v-model="testEmail" type="email" class="m-0" placeholder="you@example.com" @keyup.enter="sendTest">
        <p v-if="testMsg" class="text-[.82rem] mt-2" :class="testMsg.includes('✓') ? 'text-[#16a34a]' : 'text-[#dc2626]'">{{ testMsg }}</p>
        <div class="flex justify-end gap-2 mt-4">
          <button class="btn ghost sm" @click="testOpen = false">Close</button>
          <button class="btn sm" :disabled="!testEmail || testSending" @click="sendTest">{{ testSending ? 'Sending…' : 'Send test' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

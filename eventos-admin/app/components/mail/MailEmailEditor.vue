<script setup lang="ts">
import type { Block, BlockType, EmailSettings } from '~/composables/useEmailBlocks'
import {
  createBlock, cloneBlock, findContext, walkBlocks,
  defaultSettings, starterBlocks, PALETTE,
} from '~/composables/useEmailBlocks'
import type { VarGroup } from '~/components/mail/MailVariableMenu.vue'

interface TemplateDto {
  id: string
  name: string
  subject?: string | null
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
const showSettingsDrawer = ref(false)
const saving = ref(false)
const dirty = ref(false)

// ── selected block lookup ────────────────────────────────────────────────
const selectedBlock = computed<Block | null>(() => {
  if (!selectedId.value) return null
  let found: Block | null = null
  walkBlocks(blocks, b => { if (b.id === selectedId.value) found = b })
  return found
})

// ── builder API (provided to nested canvas blocks) ───────────────────────
provide('emailBuilder', {
  selectedId,
  varGroups,
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
})

// mark dirty on any document change
watch([blocks, settings, name, subject, fromName, fromEmail, replyTo], () => { dirty.value = true }, { deep: true })

// ── palette add / drag ───────────────────────────────────────────────────
function addBlock(type: BlockType, index?: number) {
  const b = createBlock(type)
  if (index === undefined) blocks.push(b)
  else blocks.splice(index, 0, b)
  selectedId.value = b.id
}
const dragType = ref<BlockType | null>(null)
const dropIndex = ref<number | null>(null)
function onDrop(index: number) {
  if (dragType.value) addBlock(dragType.value, index)
  dragType.value = null
  dropIndex.value = null
}
function iconPaths(icon: string) {
  return icon.split(' M').map((s, i) => (i ? 'M' + s : s))
}
// literals kept in script so "}}" never reaches Vue's mustache parser
const VAR_EXAMPLE = '{{ variables }}'
const SUBJECT_PLACEHOLDER = "e.g. You're invited, {{ contact.first_name }}"

// ── server-rendered preview ──────────────────────────────────────────────
const previewHtml = ref('')
const previewLoading = ref(false)
async function refreshPreview() {
  previewLoading.value = true
  try {
    const res = await api<{ html: string }>('/email-templates/preview-draft', {
      method: 'POST',
      body: { subject: subject.value, blocks, settings },
    })
    previewHtml.value = res.html
  } finally {
    previewLoading.value = false
  }
}
watch(mode, m => { if (m === 'preview') refreshPreview() })

// ── persistence ──────────────────────────────────────────────────────────
function payload(status?: string) {
  return {
    name: name.value.trim() || 'Untitled email',
    subject: subject.value || null,
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
  try {
    const res = uuid.value
      ? await api<{ data: TemplateDto }>(`/email-templates/${uuid.value}`, { method: 'PUT', body: payload(status) })
      : await api<{ data: TemplateDto }>('/email-templates', { method: 'POST', body: payload(status) })
    uuid.value = res.data.id
    dirty.value = false
    emit('saved', res.data)
    return res.data
  } finally {
    saving.value = false
  }
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

onMounted(async () => {
  try { varGroups.value = (await api<{ data: VarGroup[] }>('/email-variables')).data } catch { /* non-fatal */ }
})
</script>

<template>
  <div class="fixed inset-0 z-[150] bg-[#eef0f4] flex flex-col">
    <!-- ───────── Topbar ───────── -->
    <header class="h-[58px] shrink-0 bg-white border-b border-line flex items-center gap-3 px-4">
      <button class="w-9 h-9 rounded-lg border border-line bg-white grid place-items-center cursor-pointer hover:bg-[#f5f5fa]" title="Back" @click="emit('close')">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#5f6b7a" stroke-width="2" stroke-linecap="round"><path d="M15 18l-6-6 6-6" /></svg>
      </button>
      <input v-model="name" class="m-0 w-[260px] font-semibold border-transparent hover:border-line focus:border-[#6352e7] bg-transparent" placeholder="Template name">
      <span v-if="dirty" class="text-[.72rem] text-[#b45309] bg-[#fef3c7] px-2 py-0.5 rounded-full">Unsaved</span>

      <div class="ml-auto flex items-center gap-2">
        <div class="flex bg-[#f1f1f6] rounded-lg p-0.5">
          <button class="px-3 py-1.5 rounded-md text-[.82rem] cursor-pointer" :class="mode === 'edit' ? 'bg-white shadow-sm font-semibold' : 'text-[#5f6b7a]'" @click="mode = 'edit'">Edit</button>
          <button class="px-3 py-1.5 rounded-md text-[.82rem] cursor-pointer" :class="mode === 'preview' ? 'bg-white shadow-sm font-semibold' : 'text-[#5f6b7a]'" @click="mode = 'preview'">Preview</button>
        </div>
        <button class="btn ghost sm" @click="testOpen = true">Send test</button>
        <button class="btn sm" :disabled="saving" @click="save()">{{ saving ? 'Saving…' : 'Save' }}</button>
        <button class="btn sm" style="background:#16a34a" :disabled="saving" @click="save('published')" title="Save & mark ready to use">Save & publish</button>
      </div>
    </header>

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
        <button class="w-full mt-4 text-[.78rem] text-[#6352e7] font-semibold border border-line rounded-lg py-2 cursor-pointer hover:bg-[#f5f3ff]" @click="selectedId = null; showSettingsDrawer = false">⚙ Email settings</button>
      </aside>

      <!-- ───────── Canvas ───────── -->
      <main class="flex-1 overflow-y-auto p-6" @click="selectedId = null">
        <!-- preview -->
        <div v-if="mode === 'preview'" class="h-full flex flex-col items-center">
          <div class="flex gap-2 mb-3">
            <button class="btn ghost sm" :class="device === 'desktop' ? '!border-[#6352e7] !text-[#6352e7]' : ''" @click="device = 'desktop'">🖥 Desktop</button>
            <button class="btn ghost sm" :class="device === 'mobile' ? '!border-[#6352e7] !text-[#6352e7]' : ''" @click="device = 'mobile'">📱 Mobile</button>
            <button class="btn ghost sm" @click="refreshPreview">↻ Refresh</button>
          </div>
          <iframe
            :srcdoc="previewHtml"
            class="bg-white rounded-xl shadow-lg border border-line transition-all"
            :style="{ width: device === 'mobile' ? '380px' : '760px', height: '100%', maxWidth: '100%' }"
          />
          <p v-if="previewLoading" class="text-[#8b93a7] text-[.8rem] mt-2">Rendering…</p>
        </div>

        <!-- edit canvas -->
        <div v-else class="mx-auto" :style="{ maxWidth: settings.contentWidth + 'px' }">
          <div class="rounded-xl shadow-sm overflow-hidden" :style="{ background: settings.contentBackground, fontFamily: settings.fontFamily }" @click.stop>
            <!-- top drop zone -->
            <div
              class="h-2 transition-all" :class="dropIndex === 0 ? 'bg-[#6352e7]/30 h-6' : ''"
              @dragover.prevent="dropIndex = 0" @dragleave="dropIndex = null" @drop="onDrop(0)"
            />
            <template v-for="(b, i) in blocks" :key="b.id">
              <MailCanvasBlock :block="b" />
              <div
                class="h-2 transition-all" :class="dropIndex === i + 1 ? 'bg-[#6352e7]/30 h-6' : ''"
                @dragover.prevent="dropIndex = i + 1" @dragleave="dropIndex = null" @drop="onDrop(i + 1)"
              />
            </template>
            <div v-if="!blocks.length" class="py-16 text-center text-[#8b93a7]">
              Drag a block here or click one on the left to start.
            </div>
          </div>
          <p class="text-center text-[#a8aec0] text-[.74rem] mt-3">Tip: click any element to edit it. Insert <span class="font-mono">{{ VAR_EXAMPLE }}</span> for personalization.</p>
        </div>
      </main>

      <!-- ───────── Right inspector ───────── -->
      <aside v-if="mode === 'edit'" class="w-[300px] shrink-0 bg-white border-l border-line overflow-y-auto">
        <!-- subject / sender (always) -->
        <div class="p-4 border-b border-line bg-[#fafafe]">
          <label class="text-[.76rem] font-semibold text-[#5f6b7a] flex items-center justify-between">Subject line <MailVariableMenu :groups="varGroups" compact @insert="t => subject += `{{ ${t} }}`" /></label>
          <input v-model="subject" class="m-0 mt-1" :placeholder="SUBJECT_PLACEHOLDER">
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

    <!-- send-test modal -->
    <div v-if="testOpen" class="fixed inset-0 z-[160] bg-black/35 grid place-items-center" @click.self="testOpen = false">
      <div class="bg-white rounded-2xl p-5 w-[380px] max-w-[92vw] shadow-xl">
        <h3 class="m-0 mb-1 text-[1.05rem]">Send a test email</h3>
        <p class="text-[#8b93a7] text-[.82rem] mt-0 mb-3">Variables render with sample data. Saves the template first.</p>
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

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, onBeforeRouteLeave } from 'vue-router'
import { toast } from 'vue-sonner'
import type { BuilderField, FormDesign } from '../../../../../../../utils/profileFormTypes'
import { DEFAULT_FORM_DESIGN } from '../../../../../../../utils/profileFormTypes'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string
const audience = route.params.audience as string

const OPTION_TYPES = ['select', 'multiselect', 'radio', 'checkbox']

const TYPE_LABELS: Record<string, string> = {
  text: 'Text', textarea: 'Text Area', email: 'Email', phone: 'Phone',
  number: 'Number', date: 'Date', select: 'Dropdown', multiselect: 'Multi Select',
  checkbox: 'Checkbox', radio: 'Radio', link: 'Link', file: 'File',
  rating: 'Rating', section_break: 'Section Break', recaptcha: 'reCAPTCHA',
}

const formName = ref('')
const status = ref<'draft' | 'published' | 'closed'>('draft')
const version = ref(1)
const fields = ref<BuilderField[]>([])
const selectedId = ref<string | null>(null)
const loading = ref(true)
const saving = ref(false)
const publishing = ref(false)
// Edit = the 3-pane builder; Design = appearance of the public form; Preview =
// the form rendered as end users see it (current unsaved state included).
const mode = ref<'edit' | 'design' | 'preview'>('edit')

// Appearance of the shared/embedded form, stored in forms.settings.design.
const design = ref<FormDesign>({ ...DEFAULT_FORM_DESIGN })
// The event's own colour, so "inherit" can be previewed truthfully.
const eventPrimary = ref('#6352e7')
const eventName = ref('')

// dirty = current state differs from the last loaded/saved snapshot
const snapshot = ref('')
const fingerprint = () => JSON.stringify({ n: formName.value, f: fields.value, d: design.value })
const dirty = computed(() => !loading.value && fingerprint() !== snapshot.value)

const selected = computed(() => fields.value.find(f => f._id === selectedId.value) || null)

// Approval turns a submission into a participant — that needs an email field
// the public form actually shows.
const missingPublicEmail = computed(() =>
  !fields.value.some(f =>
    f.type === 'email'
    && f.meta.visible !== false
    && (f.meta.surfaces?.public ?? true) !== false,
  ),
)

let uid = 0
const localId = () => `bf${++uid}`

function fromServer(f: any): BuilderField {
  return {
    _id: localId(),
    key: f.key,
    label: f.label || '',
    help_text: f.help_text || '',
    type: f.type,
    is_default: !!f.is_default,
    is_required: !!f.required,
    is_unique: !!f.is_unique,
    is_pii: !!f.is_pii,
    validation: f.validation || null,
    meta: {
      placeholder: f.meta?.placeholder || '',
      width: f.meta?.width === 50 ? 50 : 100,
      visible: f.meta?.visible !== false,
      show_to_others: f.meta?.show_to_others === true,
      surfaces: {
        registration: (f.meta?.surfaces?.registration ?? true) !== false,
        onboarding: (f.meta?.surfaces?.onboarding ?? true) !== false,
        public: (f.meta?.surfaces?.public ?? true) !== false,
      },
    },
    options: (f.options || []).map((o: any) => ({ label: o.label, value: o.value })),
  }
}

async function load() {
  loading.value = true
  try {
    const r = await api<any>(`/events/${id}/profile-forms/${audience}`)
    formName.value = r.data.name
    status.value = r.data.status
    version.value = r.data.version
    fields.value = (r.data.fields || []).map(fromServer)
    design.value = { ...DEFAULT_FORM_DESIGN, ...(r.data.settings?.design || {}) }
    snapshot.value = fingerprint()
    if (!selectedId.value && fields.value.length) selectedId.value = fields.value[0]._id
  } catch {
    toast.error('Could not load the form.')
  } finally { loading.value = false }
}

// ── Add / remove / reorder ─────────────────────────────────────────

const slugify = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '')

function makeField(type: string): BuilderField {
  const label = type === 'section_break' ? 'Section' : TYPE_LABELS[type] || 'Field'
  return {
    _id: localId(),
    key: '', // assigned at save time from the final label
    label,
    help_text: '',
    type,
    is_default: false,
    is_required: false,
    is_unique: false,
    is_pii: false,
    validation: type === 'rating' ? { max: 5 } : null,
    meta: { placeholder: '', width: 100, visible: true, show_to_others: false, surfaces: { registration: true, onboarding: true, public: true } },
    options: OPTION_TYPES.includes(type) ? [{ label: 'Option 1' }, { label: 'Option 2' }] : [],
  }
}

function addField(type: string, index?: number) {
  if (type === 'recaptcha' && fields.value.some(f => f.type === 'recaptcha')) {
    toast.info('The form already has a reCAPTCHA field.')
    return
  }
  const f = makeField(type)
  if (index === undefined || index < 0 || index > fields.value.length) fields.value.push(f)
  else fields.value.splice(index, 0, f)
  selectedId.value = f._id
}

/** Copy a field's settings into a new one below it — an empty key makes it a
 *  fresh field at save time, so the original keeps its answers. */
function duplicateField(f: BuilderField) {
  const copy: BuilderField = JSON.parse(JSON.stringify(f))
  copy._id = localId()
  copy.key = ''
  copy.is_default = false
  copy.is_unique = false // a copied unique field would collide by definition
  copy.label = `${f.label || TYPE_LABELS[f.type]} copy`
  fields.value.splice(fields.value.findIndex(x => x._id === f._id) + 1, 0, copy)
  selectedId.value = copy._id
}

function removeField(f: BuilderField) {
  if (f.is_default) return
  const i = fields.value.findIndex(x => x._id === f._id)
  if (i >= 0) fields.value.splice(i, 1)
  if (selectedId.value === f._id) selectedId.value = fields.value[Math.min(i, fields.value.length - 1)]?._id || null
}

function toggleVisible(f: BuilderField) {
  f.meta.visible = !(f.meta.visible !== false)
}

// ── Drag & drop (palette inserts + canvas reorder) ─────────────────

const dragIndex = ref<number | null>(null)   // internal reorder
const dropIndex = ref<number | null>(null)   // palette insertion marker

function onRowDragStart(i: number, e: DragEvent) {
  dragIndex.value = i
  e.dataTransfer!.effectAllowed = 'move'
}

function onRowDragOver(i: number, e: DragEvent) {
  e.preventDefault()
  if (dragIndex.value !== null) {
    if (dragIndex.value === i) return
    const arr = fields.value
    const [moved] = arr.splice(dragIndex.value, 1)
    arr.splice(i, 0, moved)
    dragIndex.value = i
  } else if (e.dataTransfer?.types.includes('application/x-field-type')) {
    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect()
    dropIndex.value = e.clientY < rect.top + rect.height / 2 ? i : i + 1
  }
}

function onCanvasDragOver(e: DragEvent) {
  if (e.dataTransfer?.types.includes('application/x-field-type')) {
    e.preventDefault()
    if (dropIndex.value === null) dropIndex.value = fields.value.length
  }
}

function onDrop(e: DragEvent) {
  const type = e.dataTransfer?.getData('application/x-field-type')
  if (type) {
    e.preventDefault()
    addField(type, dropIndex.value ?? fields.value.length)
  }
  dragIndex.value = null
  dropIndex.value = null
}

function onDragEnd() { dragIndex.value = null; dropIndex.value = null }

// ── Persist ────────────────────────────────────────────────────────

function payloadFields() {
  const used = new Set(fields.value.filter(f => f.key).map(f => f.key))
  return fields.value.map((f) => {
    let key = f.key
    if (!key) {
      const base = slugify(f.label) || f.type
      key = base
      let n = 2
      while (used.has(key)) key = `${base}_${n++}`
      used.add(key)
    }
    return {
      key,
      type: f.type,
      label: f.label || null,
      help_text: f.help_text || null,
      is_required: f.is_required,
      is_unique: f.is_unique,
      is_pii: f.is_pii,
      validation: f.validation,
      meta: {
        placeholder: f.meta.placeholder || null,
        width: f.meta.width === 50 ? 50 : 100,
        visible: f.meta.visible !== false,
        show_to_others: f.meta.show_to_others === true,
        surfaces: {
          registration: (f.meta.surfaces?.registration ?? true) !== false,
          onboarding: (f.meta.surfaces?.onboarding ?? true) !== false,
          public: (f.meta.surfaces?.public ?? true) !== false,
        },
      },
      options: OPTION_TYPES.includes(f.type)
        ? f.options.filter(o => o.label.trim()).map(o => ({ label: o.label.trim(), value: o.value || o.label.trim() }))
        : [],
    }
  })
}

async function save(silent = false): Promise<boolean> {
  saving.value = true
  try {
    await api(`/events/${id}/profile-forms/${audience}`, {
      method: 'PUT',
      body: { name: formName.value, fields: payloadFields(), design: design.value },
    })
    const keep = selected.value?.key
    await load()
    if (keep) selectedId.value = fields.value.find(f => f.key === keep)?._id || selectedId.value
    if (!silent) toast.success('Form saved')
    return true
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save the form.')
    return false
  } finally { saving.value = false }
}

async function publish() {
  publishing.value = true
  try {
    if (!(await save(true))) return
    await api(`/events/${id}/profile-forms/${audience}/publish`, { method: 'POST' })
    await load()
    toast.success(`Form published (v${version.value})`)
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not publish the form.')
  } finally { publishing.value = false }
}

// ── Leave guards ───────────────────────────────────────────────────

onBeforeRouteLeave(() => {
  if (dirty.value && !confirm('You have unsaved changes. Leave without saving?')) return false
})
const beforeUnload = (e: BeforeUnloadEvent) => { if (dirty.value) e.preventDefault() }

/** The event's own branding, so "inherit the event colour" previews truthfully. */
async function loadEvent() {
  try {
    const [ev, settings] = await Promise.all([
      api<any>(`/events/${id}`),
      api<any>(`/events/${id}/settings`),
    ])
    eventName.value = ev.data?.name || ''
    eventPrimary.value = settings.data?.theme?.primary || '#6352e7'
  } catch { /* the defaults are fine for a preview */ }
}

onMounted(() => { load(); loadEvent(); window.addEventListener('beforeunload', beforeUnload) })
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
</script>

<template>
  <div>
    <!-- ── Header ─────────────────────────────────────────────── -->
    <div class="flex items-center gap-3 mb-5 flex-wrap">
      <NuxtLink :to="`/org/events/${id}/settings/profile`" class="text-[#6352e7] text-[.9rem] font-semibold no-underline hover:underline">Profile</NuxtLink>
      <span class="text-faint">›</span>
      <h2 class="section-title m-0">{{ formName || '…' }} Fields</h2>
      <span class="badge" :class="status === 'published' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
        {{ status === 'published' ? `Published · v${version}` : 'Draft' }}
      </span>
      <span v-if="dirty" class="text-[.78rem] font-semibold text-amber-600">● Unsaved changes</span>
      <div class="flex-1" />
      <div class="flex items-center border border-line rounded-lg overflow-hidden mr-1">
        <button
          v-for="m in (['edit', 'design', 'preview'] as const)" :key="m"
          class="px-3.5 py-[7px] text-[.8rem] font-semibold cursor-pointer border-none transition-colors duration-150 capitalize"
          :class="mode === m ? 'bg-[#6352e7] text-white' : 'bg-white text-[#5f6b7a] hover:bg-[#f3f0ff]'"
          @click="mode = m"
        >{{ m }}</button>
      </div>
      <button class="btn ghost" :disabled="saving || publishing" @click="save()">{{ saving ? 'Saving…' : 'Save' }}</button>
      <button class="btn" :disabled="saving || publishing" @click="publish">{{ publishing ? 'Publishing…' : 'Publish Form' }}</button>
    </div>

    <div v-if="loading" class="card muted text-center py-16">Loading form…</div>

    <!-- ── Design mode: appearance controls beside a live preview ─ -->
    <div v-else-if="mode === 'design'" class="grid grid-cols-[340px_minmax(0,1fr)] gap-5 items-start">
      <div class="sticky top-[86px] max-h-[calc(100vh-120px)] overflow-y-auto">
        <ProfileFormDesign :design="design" :event-primary="eventPrimary" :event-id="id" />
      </div>
      <ProfileFormPreview
        :fields="fields" :name="formName" :audience="audience"
        :design="design" :event-primary="eventPrimary" :event-name="eventName"
      />
    </div>

    <!-- ── Preview mode: the form as end users will see it ────── -->
    <ProfileFormPreview
      v-else-if="mode === 'preview'"
      :fields="fields" :name="formName" :audience="audience"
      :design="design" :event-primary="eventPrimary" :event-name="eventName"
    />

    <div v-else class="grid grid-cols-[280px_minmax(0,1fr)_340px] gap-5 items-start">
      <!-- ── Palette ──────────────────────────────────────────── -->
      <div class="card sticky top-[86px] max-h-[calc(100vh-120px)] overflow-y-auto">
        <ProfileFieldPalette @add="addField($event)" />
      </div>

      <!-- ── Canvas ───────────────────────────────────────────── -->
      <div class="card min-h-[420px]" @dragover="onCanvasDragOver" @drop="onDrop">
        <div class="flex items-center gap-2 mb-4">
          <h3 class="m-0 text-[1.02rem] font-bold text-ink">Form fields</h3>
          <span class="muted text-[.8rem]">{{ fields.filter(f => f.type !== 'section_break').length }} fields</span>
        </div>

        <p v-if="missingPublicEmail" class="text-[.78rem] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mb-3">
          No email field is shown on the public form — you won't be able to convert
          submissions into participants when approving them.
        </p>

        <div v-if="!fields.length" class="border-2 border-dashed border-[#d7dae1] rounded-xl py-16 text-center muted">
          Drag fields here from the left to build your form.
        </div>

        <div class="flex flex-col gap-2">
          <template v-for="(f, i) in fields" :key="f._id">
            <div v-if="dropIndex === i" class="h-[3px] rounded bg-[#6352e7]" />
            <div
              class="group flex items-center gap-3 px-4 py-3 rounded-xl border bg-white cursor-pointer transition-[border-color,background,opacity] duration-150"
              :class="[
                selectedId === f._id ? 'border-[#6352e7] bg-[#f6f4ff] shadow-[0_1px_6px_rgba(99,82,231,.14)]' : 'border-line hover:border-[#c9c2f5]',
                f.meta.visible === false ? 'opacity-55' : '',
                f.type === 'section_break' ? 'border-dashed' : '',
                dragIndex === i ? 'opacity-40' : '',
              ]"
              draggable="true"
              @click="selectedId = f._id"
              @dragstart="onRowDragStart(i, $event)"
              @dragover="onRowDragOver(i, $event)"
              @dragend="onDragEnd"
            >
              <span class="cursor-grab text-[#b8bcc6] text-[1.02rem] select-none leading-none" title="Drag to reorder">⠿</span>

              <div class="flex-1 min-w-0">
                <div class="font-bold text-[.9rem] truncate" :class="selectedId === f._id ? 'text-[#6352e7]' : 'text-ink'">
                  {{ f.label || TYPE_LABELS[f.type] }}
                  <span v-if="f.is_required" class="text-[#dc2626]">*</span>
                </div>
                <div class="muted text-[.76rem] flex items-center gap-2">
                  {{ TYPE_LABELS[f.type] || f.type }}
                  <span v-if="f.meta.width === 50" class="text-[.68rem] font-bold bg-[#eef2ff] text-[#6352e7] rounded px-1.5 py-px">50%</span>
                  <span v-if="f.meta.visible === false" class="text-[.68rem] font-bold bg-gray-100 text-gray-500 rounded px-1.5 py-px">Hidden</span>
                </div>
              </div>

              <button
                v-if="f.type !== 'section_break' && f.type !== 'recaptcha'"
                class="w-8 h-8 rounded-lg bg-transparent border-none cursor-pointer text-[#5f6b7a] opacity-0 group-hover:opacity-100 transition-opacity grid place-items-center shrink-0 hover:text-[#6352e7]"
                title="Duplicate field"
                @click.stop="duplicateField(f)"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>

              <button
                v-if="!f.is_default"
                class="w-8 h-8 rounded-lg bg-transparent border-none cursor-pointer text-[#e11d48] opacity-0 group-hover:opacity-100 transition-opacity grid place-items-center shrink-0"
                title="Delete field"
                @click.stop="removeField(f)"
              >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
              </button>

              <button
                class="w-8 h-8 rounded-lg bg-transparent border-none cursor-pointer shrink-0 grid place-items-center"
                :class="f.meta.visible === false ? 'text-faint' : 'text-ink'"
                :title="f.meta.visible === false ? 'Hidden — click to show' : 'Visible — click to hide'"
                @click.stop="toggleVisible(f)"
              >
                <svg v-if="f.meta.visible !== false" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg v-else width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19M6.61 6.61A13.53 13.53 0 0 0 2 12s3.5 8 10 8a9.74 9.74 0 0 0 5.39-1.61M2 2l20 20"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/></svg>
              </button>
            </div>
          </template>
          <div v-if="dropIndex === fields.length" class="h-[3px] rounded bg-[#6352e7]" />
        </div>
      </div>

      <!-- ── Properties ───────────────────────────────────────── -->
      <div class="card sticky top-[86px] max-h-[calc(100vh-120px)] overflow-y-auto">
        <ProfileFieldProperties :field="selected" :audience="audience" />
      </div>
    </div>
  </div>
</template>

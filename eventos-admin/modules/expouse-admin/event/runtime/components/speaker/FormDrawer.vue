<script setup lang="ts">
import { reactive, ref } from 'vue'

interface SpeakerCategory {
  id: string
  name: string
}

interface DraftShape {
  name: string
  email: string
  designation: string
  company: string
  category: string
  presentation_title: string
  presentation_file: string | null
  presentation_file_name: string
  bio: string
  image_url: string | null
  facebook: string
  linkedin: string
  twitter: string
  instagram: string
  whatsapp: string
  tags: string[]
  can_rate: boolean
  is_featured: boolean
  is_public: boolean
}

// `speaker` is the row being edited (any-shaped superset of DraftShape) or null
// when adding. The component is mounted fresh on every open (parent uses v-if),
// so we can seed the draft once here in setup.
const props = defineProps<{
  eventId: string
  speaker?: (DraftShape & Record<string, any>) | null
  categories?: SpeakerCategory[]
  saving?: boolean
  error?: string
  catBusy?: boolean
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: DraftShape): void
  (e: 'add-category', name: string): void
  (e: 'rename-category', payload: { id: string, name: string }): void
  (e: 'remove-category', id: string): void
}>()

const { upload } = useUpload()

const editing = !!props.speaker

function freshDraft(): DraftShape {
  return {
    name: '', email: '', designation: '', company: '',
    category: '', presentation_title: '', presentation_file: null, presentation_file_name: '',
    bio: '', image_url: null,
    facebook: '', linkedin: '', twitter: '', instagram: '', whatsapp: '',
    tags: [], can_rate: false, is_featured: false, is_public: true,
  }
}

const draft = reactive<DraftShape>(freshDraft())

// The backend stores a single `name`, but the form splits it into first / last.
// Seed both from the existing row on edit; recombine on save.
const firstName = ref('')
const lastName  = ref('')

if (props.speaker) {
  const s = props.speaker
  Object.assign(draft, {
    name: s.name, email: s.email, designation: s.designation,
    company: s.company, category: s.category,
    presentation_title: s.presentation_title,
    presentation_file: s.presentation_file,
    presentation_file_name: s.presentation_file_name,
    bio: s.bio, image_url: s.image_url,
    facebook: s.facebook, linkedin: s.linkedin, twitter: s.twitter,
    instagram: s.instagram, whatsapp: s.whatsapp,
    tags: [...(s.tags ?? [])], can_rate: s.can_rate,
    is_featured: s.is_featured, is_public: s.is_public,
  })
  const parts = (s.name ?? '').trim().split(/\s+/)
  firstName.value = parts.shift() ?? ''
  lastName.value  = parts.join(' ')
}

// ── Category select ──────────────────────────────────────────────────────────

function onAddCategory(name: string) {
  draft.category = name   // optimistically select the freshly added category
  emit('add-category', name)
}

// ── Presentation file ────────────────────────────────────────────────────────

const presUploading = ref(false)

async function onPresentationFile(e: Event) {
  const input = e.target as HTMLInputElement
  const f = input.files?.[0]
  if (!f) return
  presUploading.value = true
  try {
    const r = await upload(f, { collection: 'document' })
    draft.presentation_file = r.url
    draft.presentation_file_name = f.name
  } finally {
    presUploading.value = false
    input.value = ''
  }
}

function clearPresentationFile() {
  draft.presentation_file = null
  draft.presentation_file_name = ''
}

// ── Tags ─────────────────────────────────────────────────────────────────────

const tagInput = ref('')

function addTag() {
  const val = tagInput.value.replace(/,\s*$/, '').trim()
  if (val && !draft.tags.includes(val)) draft.tags.push(val)
  tagInput.value = ''
}

function removeTag(i: number) {
  draft.tags.splice(i, 1)
}

function onTagKey(e: KeyboardEvent) {
  if (e.key === 'Enter' || e.key === ',') {
    e.preventDefault()
    addTag()
  }
}

// ── Save ─────────────────────────────────────────────────────────────────────

function save() {
  draft.name = [firstName.value.trim(), lastName.value.trim()].filter(Boolean).join(' ')
  emit('save', { ...draft })
}
</script>

<template>
  <Drawer :title="editing ? 'Edit Speaker' : 'Add Speaker'" @close="emit('close')">
    <!-- ── Basic Details ─────────────────────────────────────────────────── -->
    <h3 class="text-[1rem] font-bold text-ink m-0 mb-4">Basic Details</h3>

    <!-- Image -->
    <div class="mb-5">
      <FormField label="Image">
        <ImageField
          :model-value="draft.image_url"
          :aspect="1"
          :output-width="400"
          :output-height="400"
          collection="avatar"
          card-width="96px"
          :gallery-path="`/events/${eventId}/gallery`"
          @update:model-value="draft.image_url = (Array.isArray($event) ? $event[0] : $event) || null"
        />
      </FormField>
    </div>

    <!-- Name -->
    <div class="mb-4 grid grid-cols-2 gap-3">
      <AppInput v-model="firstName" label="First Name" required placeholder="Enter First Name" />
      <AppInput v-model="lastName"  label="Last Name"  placeholder="Enter Last Name" />
    </div>

    <div class="mb-4 flex flex-col gap-4">
      <AppInput v-model="draft.email"       label="Email"          required type="email" placeholder="Enter Email" />
      <AppInput v-model="draft.designation" label="Designation"    placeholder="Enter Designation" />
      <AppInput v-model="draft.company"     label="Company Name"   placeholder="Enter Company Name" />
    </div>

    <!-- Category -->
    <div class="mb-4">
      <FormField label="Category">
        <SpeakerCategorySelect
          v-model="draft.category"
          :categories="categories"
          :busy="catBusy"
          placeholder="Select Category"
          @add="onAddCategory"
          @rename="emit('rename-category', $event)"
          @remove="emit('remove-category', $event)"
        />
      </FormField>
    </div>

    <!-- Bio -->
    <div class="mb-6">
      <FormField label="Bio">
        <DescriptionEditor
          v-model="draft.bio"
          :toolbar="['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link']"
        />
      </FormField>
    </div>

    <!-- ── Presentation Details ──────────────────────────────────────────── -->
    <h3 class="text-[1rem] font-bold text-ink m-0 mb-4 pt-2 border-t border-line">Presentation Details</h3>

    <div class="mb-4">
      <AppInput v-model="draft.presentation_title" label="Presentation Title" placeholder="Enter Presentation Title" />
    </div>

    <div class="mb-6">
      <FormField label="Presentation File" hint="DOC, PPT, PDF | 5MB (Maximum)">
        <div v-if="draft.presentation_file" class="flex items-center justify-between gap-2 border border-line rounded-[11px] px-3 py-2.5">
          <a :href="draft.presentation_file" target="_blank" class="text-brand text-[.85rem] truncate">
            {{ draft.presentation_file_name || 'View file' }}
          </a>
          <button type="button" class="bg-transparent border-0 cursor-pointer text-[#dc2626] text-[.85rem]" @click="clearPresentationFile">Remove</button>
        </div>
        <label v-else class="flex items-center border border-line rounded-[11px] overflow-hidden cursor-pointer hover:border-brand">
          <span class="px-3.5 py-2.5 bg-[#f2f1fb] text-brand font-[650] text-[.82rem] shrink-0">Choose File</span>
          <span class="px-3 text-muted text-[.85rem] truncate">{{ presUploading ? 'Uploading…' : 'No File Chosen' }}</span>
          <input type="file" class="hidden" accept=".pdf,.ppt,.pptx,.doc,.docx" @change="onPresentationFile">
        </label>
      </FormField>
    </div>

    <!-- ── Social Links ──────────────────────────────────────────────────── -->
    <h3 class="text-[1rem] font-bold text-ink m-0 mb-4 pt-2 border-t border-line">Social Links</h3>

    <div class="mb-4 flex flex-col gap-3">
      <AppInput v-model="draft.facebook" placeholder="Facebook URL">
        <template #suffix>
          <span class="w-7 h-7 rounded-md bg-[#f2f3f5] grid place-items-center text-[#1877f2]">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 8.5H14c-.6 0-1 .4-1 1V11h2.5l-.4 2.5H13V20h-2.5v-6.5H8.5V11h2V9c0-1.9 1.1-3 2.8-3H15.5v2.5Z"/></svg>
          </span>
        </template>
      </AppInput>

      <AppInput v-model="draft.linkedin" placeholder="LinkedIn URL">
        <template #suffix>
          <span class="w-7 h-7 rounded-md bg-[#f2f3f5] grid place-items-center text-[#0a66c2]">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M6.94 8.5H4.5V20h2.44V8.5ZM5.72 4.5a1.4 1.4 0 100 2.8 1.4 1.4 0 000-2.8ZM20 20h-2.44v-6c0-1.5-.54-2.2-1.6-2.2-.85 0-1.35.57-1.57 1.12-.08.2-.1.47-.1.75V20H11.9s.03-9.4 0-11.5h2.44v1.63c.32-.5.9-1.2 2.2-1.2 1.6 0 2.9 1.05 2.9 3.3V20Z"/></svg>
          </span>
        </template>
      </AppInput>

      <AppInput v-model="draft.twitter" placeholder="Twitter URL">
        <template #suffix>
          <span class="w-7 h-7 rounded-md bg-[#f2f3f5] grid place-items-center text-ink">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 3H21l-6.6 7.5L22 21h-6l-4.7-6-5.4 6H3l7-8L2 3h6.1l4.2 5.6L18.9 3Zm-2.1 16.2h1.2L7.3 4.7H6L16.8 19.2Z"/></svg>
          </span>
        </template>
      </AppInput>

      <AppInput v-model="draft.instagram" placeholder="Instagram URL">
        <template #suffix>
          <span class="w-7 h-7 rounded-md bg-[#f2f3f5] grid place-items-center text-[#c13584]">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="4.5"/><circle cx="12" cy="12" r="3.2"/><circle cx="16.6" cy="7.4" r="1" fill="currentColor" stroke="none"/></svg>
          </span>
        </template>
      </AppInput>

      <!-- WhatsApp: fixed wa.me prefix + number -->
      <div class="flex items-center border border-line rounded-[11px] overflow-hidden focus-within:border-brand">
        <span class="px-3 py-2.5 bg-[#f2f3f5] text-muted text-[.85rem] shrink-0 border-r border-line">https://wa.me/</span>
        <input
          v-model="draft.whatsapp"
          type="text"
          placeholder="Enter WhatsApp Number"
          class="flex-1 min-w-0 border-0 shadow-none! focus:shadow-none! m-0 text-[.85rem]"
        >
        <span class="w-7 h-7 mr-2 rounded-md bg-[#f2f3f5] grid place-items-center text-[#25d366] shrink-0">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3a9 9 0 00-7.7 13.6L3 21l4.6-1.2A9 9 0 1012 3Zm0 16.3a7.3 7.3 0 01-3.7-1l-.27-.16-2.73.72.73-2.66-.18-.28A7.3 7.3 0 1112 19.3Zm4-5.3c-.22-.11-1.3-.64-1.5-.71-.2-.08-.35-.11-.5.1-.14.22-.57.72-.7.87-.13.15-.26.16-.48.05a6 6 0 01-1.76-1.08 6.6 6.6 0 01-1.22-1.52c-.13-.22 0-.34.1-.45.1-.1.22-.26.33-.4.11-.13.14-.22.22-.37.07-.15.03-.28-.02-.4-.05-.1-.5-1.2-.68-1.65-.18-.43-.36-.37-.5-.38h-.42c-.15 0-.4.06-.6.28-.2.22-.79.77-.79 1.88s.81 2.17.92 2.32c.11.15 1.6 2.44 3.87 3.42.54.23.96.37 1.29.48.54.17 1.03.15 1.42.09.43-.06 1.3-.53 1.49-1.05.18-.51.18-.95.13-1.04-.05-.09-.2-.14-.42-.25Z"/></svg>
        </span>
      </div>
    </div>

    <!-- Custom Tags -->
    <div class="mb-5">
      <FormField label="Custom Tags">
        <div v-if="draft.tags.length" class="flex flex-wrap gap-1.5 mb-2">
          <span
            v-for="(tag, i) in draft.tags" :key="i"
            class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-brand-soft text-brand text-[.8rem] font-medium"
          >
            {{ tag }}
            <button
              class="bg-transparent border-0 p-0 cursor-pointer text-brand leading-none text-[.9rem]"
              @click="removeTag(i)"
            >×</button>
          </span>
        </div>
        <AppInput
          v-model="tagInput"
          placeholder="Add tag & press enter"
          @keydown="onTagKey"
          @blur="addTag"
        />
      </FormField>
    </div>

    <!-- Options -->
    <div class="mb-5 flex flex-col gap-3 pt-4 border-t border-line">
      <AppCheckbox v-model="draft.can_rate"    label="Attendees can rate this speaker" />
      <AppCheckbox v-model="draft.is_featured" label="Featured Speaker" />
      <AppCheckbox v-model="draft.is_public"   label="Public speaker data" />
    </div>

    <p v-if="error" class="error mt-3">{{ error }}</p>

    <div class="modal-actions border-t border-line pt-4 mt-5">
      <button
        class="btn"
        :disabled="!firstName.trim() || !draft.email.trim() || saving"
        @click="save"
      >
        {{ saving ? 'Saving…' : editing ? 'Update Speaker' : 'Add Speaker' }}
      </button>
      <button class="btn ghost" @click="emit('close')">Cancel</button>
    </div>
  </Drawer>
</template>

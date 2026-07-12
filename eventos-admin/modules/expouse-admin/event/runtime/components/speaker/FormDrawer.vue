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
  emit('save', { ...draft })
}
</script>

<template>
  <Drawer :title="editing ? 'Edit Speaker' : 'Add Speaker'" @close="emit('close')">
    <!-- Photo -->
    <div class="mb-5">
      <FormField label="Photo">
        <ImageField
          :model-value="draft.image_url"
          :aspect="1"
          :output-width="400"
          :output-height="400"
          collection="avatar"
          card-width="160px"
          hint="Square image recommended"
          :gallery-path="`/events/${eventId}/gallery`"
          @update:model-value="draft.image_url = (Array.isArray($event) ? $event[0] : $event) || null"
        />
      </FormField>
    </div>

    <!-- Basic info -->
    <div class="mb-5 flex flex-col gap-3">
      <AppInput v-model="draft.name"        label="Full Name"             required placeholder="e.g. Jane Smith" />
      <AppInput v-model="draft.email"       label="Email Address"         required type="email" placeholder="jane@example.com" />
      <AppInput v-model="draft.designation" label="Designation"           placeholder="e.g. CEO" />
      <AppInput v-model="draft.company"     label="Company / Organisation" placeholder="Acme Corp" />
    </div>

    <!-- Category (optional) -->
    <div class="mb-5">
      <FormField label="Category" hint="Optional. Group speakers by topic or track.">
        <SpeakerCategorySelect
          v-model="draft.category"
          :categories="categories"
          :busy="catBusy"
          @add="onAddCategory"
          @rename="emit('rename-category', $event)"
          @remove="emit('remove-category', $event)"
        />
      </FormField>
    </div>

    <!-- Presentation -->
    <div class="mb-5 flex flex-col gap-3">
      <AppInput v-model="draft.presentation_title" label="Presentation Title" placeholder="e.g. Scaling engineering teams" />
      <FormField label="Presentation File" hint="PDF, PPT or DOC, up to 20 MB.">
        <div v-if="draft.presentation_file" class="flex items-center justify-between gap-2 border border-line rounded-[11px] px-3 py-2.5">
          <a :href="draft.presentation_file" target="_blank" class="text-brand text-[.85rem] truncate">
            {{ draft.presentation_file_name || 'View file' }}
          </a>
          <button type="button" class="bg-transparent border-0 cursor-pointer text-[#dc2626] text-[.85rem]" @click="clearPresentationFile">Remove</button>
        </div>
        <label v-else class="flex items-center justify-center border border-dashed border-[#d7dae1] rounded-[11px] px-3 py-3 cursor-pointer text-muted text-[.85rem] hover:border-brand">
          <span>{{ presUploading ? 'Uploading…' : '+ Upload presentation file' }}</span>
          <input type="file" class="hidden" accept=".pdf,.ppt,.pptx,.doc,.docx,.xls,.xlsx,.csv,.txt,.key" @change="onPresentationFile">
        </label>
      </FormField>
    </div>

    <!-- Bio -->
    <div class="mb-5">
      <AppTextarea v-model="draft.bio" label="Bio" :rows="4" placeholder="Short biography…" />
    </div>

    <!-- Social links -->
    <div class="mb-5 flex flex-col gap-3">
      <p class="text-muted text-[.85rem] mb-0 mt-0 font-medium">Social Links</p>
      <AppInput v-model="draft.linkedin"  placeholder="LinkedIn URL" />
      <AppInput v-model="draft.twitter"   placeholder="Twitter / X URL" />
      <AppInput v-model="draft.facebook"  placeholder="Facebook URL" />
      <AppInput v-model="draft.instagram" placeholder="Instagram URL" />
      <AppInput v-model="draft.whatsapp"  placeholder="WhatsApp number" />
    </div>

    <!-- Tags -->
    <div class="mb-5">
      <FormField label="Tags">
        <div class="flex flex-wrap gap-1.5 mb-2">
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
          placeholder="Type a tag and press Enter or comma"
          @keydown="onTagKey"
          @blur="addTag"
        />
      </FormField>
    </div>

    <!-- Options -->
    <div class="mb-5 flex flex-col gap-3">
      <AppCheckbox v-model="draft.can_rate"    label="Attendees can rate this speaker" description="Show a rating widget on the speaker's profile" />
      <AppCheckbox v-model="draft.is_featured" label="Featured speaker" description="Highlighted in the event showcase" />
      <AppCheckbox v-model="draft.is_public"   label="Public speaker data" description="Visible to all event attendees" />
    </div>

    <p v-if="error" class="error mt-3">{{ error }}</p>

    <div class="modal-actions border-t border-line pt-4 mt-5">
      <button class="btn ghost" @click="emit('close')">Cancel</button>
      <button
        class="btn"
        :disabled="!draft.name.trim() || !draft.email.trim() || saving"
        @click="save"
      >
        {{ saving ? 'Saving…' : editing ? 'UPDATE' : 'ADD' }}
      </button>
    </div>
  </Drawer>
</template>

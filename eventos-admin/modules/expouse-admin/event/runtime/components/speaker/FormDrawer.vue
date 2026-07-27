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
        <SessionDescriptionEditor
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
    <div class="border-t border-line my-4" />

    <!-- ── Social Links ──────────────────────────────────────────────────── -->
    <p class="font-bold text-lg text-ink m-0 mb-3">Social Links</p>
    <div class="mb-4 flex flex-col">
        <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
          <input v-model="draft.facebook" placeholder="Facebook URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="22" viewBox="0 0 13 22" fill="none">
              <path d="M12 1H9C7.67392 1 6.40215 1.52678 5.46447 2.46447C4.52678 3.40215 4 4.67392 4 6V9H1V13H4V21H8V13H11L12 9H8V6C8 5.73478 8.10536 5.48043 8.29289 5.29289C8.48043 5.10536 8.73478 5 9 5H12V1Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
          <input v-model="draft.linkedin" placeholder="LinkedIn URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
              <path d="M6 9H2V21H6V9Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M16 8C17.5913 8 19.1174 8.63214 20.2426 9.75736C21.3679 10.8826 22 12.4087 22 14V21H18V14C18 13.4696 17.7893 12.9609 17.4142 12.5858C17.0391 12.2107 16.5304 12 16 12C15.4696 12 14.9609 12.2107 14.5858 12.5858C14.2107 12.9609 14 13.4696 14 14V21H10V14C10 12.4087 10.6321 10.8826 11.7574 9.75736C12.8826 8.63214 14.4087 8 16 8V8Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M4 6C5.10457 6 6 5.10457 6 4C6 2.89543 5.10457 2 4 2C2.89543 2 2 2.89543 2 4C2 5.10457 2.89543 6 4 6Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
          <input v-model="draft.twitter" placeholder="Twitter URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="18" viewBox="0 0 20 18" fill="none">
                <path d="M15.7512 0H18.818L12.1179 7.62462L20 18H13.8284L8.99458 11.7074L3.46359 18H0.394938L7.5613 9.84461L0 0H6.32828L10.6976 5.75169L15.7512 0ZM14.6748 16.1723H16.3742L5.4049 1.73169H3.58133L14.6748 16.1723Z" fill="#64676A"/>
            </svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
          <input v-model="draft.instagram" placeholder="Instagram URL" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                <path d="M17 2H7C4.23858 2 2 4.23858 2 7V17C2 19.7614 4.23858 22 7 22H17C19.7614 22 22 19.7614 22 17V7C22 4.23858 19.7614 2 17 2Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M16 11.3703C16.1234 12.2025 15.9812 13.0525 15.5937 13.7993C15.2062 14.5461 14.5931 15.1517 13.8416 15.53C13.0901 15.9082 12.2384 16.0399 11.4077 15.9062C10.5771 15.7726 9.80971 15.3804 9.21479 14.7855C8.61987 14.1905 8.22768 13.4232 8.09402 12.5925C7.96035 11.7619 8.09202 10.9102 8.47028 10.1587C8.84854 9.40716 9.45414 8.79404 10.2009 8.40654C10.9477 8.01904 11.7977 7.87689 12.63 8.0003C13.4789 8.12619 14.2648 8.52176 14.8716 9.12861C15.4785 9.73545 15.8741 10.5214 16 11.3703Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M17.5 6.5H17.51" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
        </div>

        <div class="flex items-center border border-line rounded-lg overflow-hidden my-2 bg-white">
          <span class="px-3 py-2.5 text-[.82rem] font-semibold text-muted bg-[#f7f8fa] border-r border-line whitespace-nowrap shrink-0">https://wa.me/</span>
          <input v-model="draft.whatsapp" placeholder="Enter WhatsApp number" style="border:0;box-shadow:none;margin:0;flex:1;border-radius:0;outline:none;">
          <div class="w-10 h-10 flex items-center justify-center shrink-0 m-1 rounded-lg bg-[#F7F7FB]">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22" fill="none">
                <path d="M16.0402 13.1826C15.7672 13.0461 14.4209 12.386 14.17 12.2958C13.9191 12.2045 13.7367 12.1594 13.5544 12.4322C13.372 12.704 12.847 13.319 12.6867 13.5005C12.5275 13.6821 12.3673 13.7041 12.0943 13.5677C11.2874 13.2479 10.5428 12.7907 9.89359 12.2166C9.29487 11.6657 8.78158 11.0294 8.37048 10.3285C8.21131 10.0557 8.3539 9.90826 8.49096 9.77293C8.61365 9.6508 8.76507 9.45496 8.90103 9.29542C9.01363 9.15769 9.10593 9.00469 9.17514 8.84102C9.21156 8.76581 9.22852 8.68273 9.22446 8.59934C9.22041 8.51594 9.19548 8.43488 9.15193 8.36352C9.0834 8.22709 8.53628 6.88589 8.30858 6.34017C8.08642 5.80986 7.86093 5.88137 7.69182 5.87257C7.53266 5.86487 7.35028 5.86267 7.16791 5.86267C7.02917 5.86652 6.89274 5.89891 6.7672 5.9578C6.64165 6.01668 6.52971 6.1008 6.4384 6.20485C6.12895 6.49656 5.88392 6.84923 5.71893 7.2404C5.55394 7.63157 5.4726 8.05266 5.48011 8.47684C5.56886 9.50473 5.95724 10.4844 6.59757 11.2957C7.77131 13.0473 9.38253 14.465 11.273 15.4095C11.783 15.6275 12.3039 15.8192 12.8337 15.9838C13.3923 16.1526 13.9827 16.1891 14.558 16.0905C14.939 16.0137 15.2998 15.859 15.6177 15.6363C15.9356 15.4136 16.2037 15.1277 16.4049 14.7966C16.5845 14.3895 16.6398 13.9388 16.5641 13.5005C16.4967 13.3861 16.3143 13.319 16.0402 13.1826V13.1826ZM18.7946 3.19349C16.9155 1.32346 14.4178 0.196881 11.7664 0.0234775C9.11503 -0.149926 6.49069 0.641668 4.38197 2.25088C2.27324 3.8601 0.823849 6.17728 0.303621 8.77101C-0.216607 11.3647 0.227784 14.0583 1.55406 16.3502L0 21.9999L5.80728 20.4849C7.41346 21.3554 9.21315 21.8116 11.042 21.8118H11.0464C13.2129 21.8107 15.3305 21.1704 17.1316 19.9718C18.9327 18.7733 20.3365 17.0702 21.1656 15.0778C21.9948 13.0854 22.2121 10.8931 21.7901 8.77785C21.3681 6.66258 20.3257 4.71928 18.7946 3.19348V3.19349ZM15.8843 18.5847C14.4343 19.4895 12.7577 19.9695 11.0464 19.9699H11.042C9.41157 19.9699 7.81118 19.5332 6.40856 18.7058L6.07586 18.5099L2.62952 19.4099L3.54914 16.0652L3.3336 15.7219C2.37701 14.2034 1.89425 12.4366 1.94637 10.645C1.99849 8.85342 2.58315 7.11742 3.62642 5.65656C4.66969 4.1957 6.12471 3.07559 7.80749 2.43786C9.49027 1.80013 11.3252 1.67342 13.0804 2.07377C14.8355 2.47411 16.4319 3.38352 17.6678 4.68701C18.9037 5.99049 19.7236 7.6295 20.0237 9.39679C20.3239 11.1641 20.0908 12.9803 19.354 14.6157C18.6172 16.2512 17.4097 17.6324 15.8843 18.5847" fill="#64676A"/>
            </svg>
          </div>
        </div>
  
    </div>

    <!-- Custom Tags -->
    <div class="mb-5">
      <FormField label="Custom Tags">
        <div v-if="draft.tags.length" class="flex flex-wrap gap-1.5 mb-2">
          <span
            v-for="(tag, i) in draft.tags" :key="i"
            class="flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-[#F0EEFD] text-brand text-[.8rem] font-medium"
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

    <div class="modal-actions border-t border-line pt-4 mt-5 justify-start">
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

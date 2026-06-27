<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api   = useApi()
const id    = route.params.id as string

// ── Types ────────────────────────────────────────────────────────────────────

interface Speaker {
  id: string
  name: string
  email: string
  designation: string
  company: string
  bio: string
  image_url: string | null
  facebook: string
  linkedin: string
  twitter: string
  instagram: string
  whatsapp: string
  tags: string[]
  is_featured: boolean
  is_public: boolean
  sort_order: number
}

interface DraftShape {
  name: string
  email: string
  designation: string
  company: string
  bio: string
  image_url: string | null
  facebook: string
  linkedin: string
  twitter: string
  instagram: string
  whatsapp: string
  tags: string[]
  is_featured: boolean
  is_public: boolean
}

// ── State ────────────────────────────────────────────────────────────────────

const speakers   = ref<Speaker[]>([])
const search     = ref('')
const drawerOpen = ref(false)
const editingId  = ref<string | null>(null)
const saving     = ref(false)
const error      = ref('')
const tagInput   = ref('')

function freshDraft(): DraftShape {
  return {
    name: '', email: '', designation: '', company: '',
    bio: '', image_url: null,
    facebook: '', linkedin: '', twitter: '', instagram: '', whatsapp: '',
    tags: [], is_featured: false, is_public: true,
  }
}

const draft = reactive<DraftShape>(freshDraft())

// ── Computed ─────────────────────────────────────────────────────────────────

const filtered = computed(() => {
  const q = search.value.toLowerCase()
  if (!q) return speakers.value
  return speakers.value.filter(s =>
    [s.name, s.company, s.designation, s.email].some(f =>
      (f ?? '').toLowerCase().includes(q)
    )
  )
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

// ── API ───────────────────────────────────────────────────────────────────────

async function load() {
  try {
    const res = await api<{ data: Speaker[] }>(`/events/${id}/speakers`)
    speakers.value = res.data
  } catch { /* */ }
}

// ── Drawer open/close ─────────────────────────────────────────────────────────

function openAdd() {
  Object.assign(draft, freshDraft())
  editingId.value = null
  error.value = ''
  tagInput.value = ''
  drawerOpen.value = true
}

function openEdit(s: Speaker) {
  Object.assign(draft, {
    name: s.name, email: s.email, designation: s.designation,
    company: s.company, bio: s.bio, image_url: s.image_url,
    facebook: s.facebook, linkedin: s.linkedin, twitter: s.twitter,
    instagram: s.instagram, whatsapp: s.whatsapp,
    tags: [...s.tags], is_featured: s.is_featured, is_public: s.is_public,
  })
  editingId.value = s.id
  error.value = ''
  tagInput.value = ''
  drawerOpen.value = true
}

// ── Save ──────────────────────────────────────────────────────────────────────

async function saveDraft() {
  error.value = ''
  saving.value = true
  try {
    const payload = { ...draft }

    if (editingId.value) {
      const res = await api<{ data: Speaker }>(`/events/${id}/speakers/${editingId.value}`, {
        method: 'PUT', body: payload,
      })
      const idx = speakers.value.findIndex(s => s.id === editingId.value)
      if (idx >= 0) speakers.value[idx] = res.data
    } else {
      const res = await api<{ data: Speaker }>(`/events/${id}/speakers`, {
        method: 'POST', body: payload,
      })
      speakers.value.push(res.data)
    }

    drawerOpen.value = false
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save speaker.'
  } finally {
    saving.value = false
  }
}

async function removeSpeaker(s: Speaker) {
  if (!confirm(`Remove speaker "${s.name}"?`)) return
  try {
    await api(`/events/${id}/speakers/${s.id}`, { method: 'DELETE' })
    speakers.value = speakers.value.filter(x => x.id !== s.id)
  } catch { /* */ }
}

// ── Tags ──────────────────────────────────────────────────────────────────────

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

// ── Init ──────────────────────────────────────────────────────────────────────

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="mb-4">
      <h2 class="section-title m-0">Speakers</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Manage the speakers who appear in your event's showcase area.</p>
    </div>

    <!-- Card -->
    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-4">
        <input
          v-model="search"
          placeholder="Search speakers…"
          class="m-0 max-w-[260px]"
        >
        <button class="btn" @click="openAdd">
          <Icon name="plus" class="w-3.75 h-3.75" /> SPEAKER
        </button>
      </div>

      <table>
        <thead>
          <tr>
            <th>SPEAKER</th>
            <th>ROLE</th>
            <th>TAGS</th>
            <th>STATUS</th>
            <th class="text-right">ACTIONS</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in filtered" :key="s.id">
            <!-- Avatar + name + email -->
            <td>
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full overflow-hidden shrink-0 bg-brand-soft flex items-center justify-center text-brand font-semibold text-[.8rem]">
                  <img v-if="s.image_url" :src="s.image_url" :alt="s.name" class="w-full h-full object-cover">
                  <span v-else>{{ initials(s.name) }}</span>
                </div>
                <div>
                  <div class="font-semibold text-ink leading-tight">{{ s.name }}</div>
                  <div class="muted text-[.8rem]">{{ s.email }}</div>
                </div>
              </div>
            </td>

            <!-- Designation / Company -->
            <td>
              <div class="text-[.88rem] text-ink">{{ s.designation || '—' }}</div>
              <div class="muted text-[.8rem]">{{ s.company }}</div>
            </td>

            <!-- Tags -->
            <td>
              <div class="flex flex-wrap gap-1">
                <span
                  v-for="tag in s.tags.slice(0, 3)" :key="tag"
                  class="px-2 py-0.5 rounded-full bg-[#f0f0f7] text-[#6352e7] text-[.75rem] font-medium"
                >{{ tag }}</span>
                <span v-if="s.tags.length > 3" class="muted text-[.75rem]">+{{ s.tags.length - 3 }}</span>
                <span v-if="!s.tags.length" class="muted text-[.8rem]">—</span>
              </div>
            </td>

            <!-- Badges -->
            <td>
              <div class="flex flex-col gap-1">
                <span
                  v-if="s.is_featured"
                  class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[.73rem] font-medium w-fit"
                >Featured</span>
                <span
                  :class="s.is_public
                    ? 'bg-green-50 text-green-700'
                    : 'bg-[#f1f1f5] text-muted'"
                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[.73rem] font-medium w-fit"
                >{{ s.is_public ? 'Public' : 'Hidden' }}</span>
              </div>
            </td>

            <!-- Actions -->
            <td class="text-right whitespace-nowrap">
              <button
                class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-brand"
                title="Edit"
                @click="openEdit(s)"
              >✎</button>
              <button
                class="bg-transparent border-0 cursor-pointer text-base px-2 py-1 text-[#dc2626]"
                title="Remove"
                @click="removeSpeaker(s)"
              >🗑</button>
            </td>
          </tr>

          <tr v-if="!filtered.length">
            <td colspan="5" class="muted text-center py-8">
              {{ search ? 'No speakers match your search.' : 'No speakers yet. Click + SPEAKER to add one.' }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add / Edit Drawer -->
    <Drawer v-if="drawerOpen" :title="editingId ? 'Edit Speaker' : 'Add Speaker'" @close="drawerOpen = false">

      <!-- Photo -->
      <div class="mb-5">
        <label class="block mb-1.5">Photo</label>
        <UploadButton
          :preview="draft.image_url"
          collection="avatar"
          @uploaded="draft.image_url = $event.url"
        />
      </div>

      <!-- Basic info -->
      <div class="mb-5">
        <label class="block mb-1.5">Basic Info</label>
        <div class="flex flex-col gap-2">
          <input v-model="draft.name" placeholder="Full name *" class="m-0">
          <input v-model="draft.email" type="email" placeholder="Email address *" class="m-0">
          <input v-model="draft.designation" placeholder="Designation (e.g. CEO)" class="m-0">
          <input v-model="draft.company" placeholder="Company / Organisation" class="m-0">
        </div>
      </div>

      <!-- Bio -->
      <div class="mb-5">
        <label class="block mb-1.5">Bio</label>
        <textarea
          v-model="draft.bio"
          rows="4"
          placeholder="Short biography…"
          class="w-full resize-y m-0"
        />
      </div>

      <!-- Social links -->
      <div class="mb-5">
        <label class="block mb-1.5">Social Links</label>
        <div class="flex flex-col gap-2">
          <input v-model="draft.linkedin" placeholder="LinkedIn URL" class="m-0">
          <input v-model="draft.twitter" placeholder="Twitter / X URL" class="m-0">
          <input v-model="draft.facebook" placeholder="Facebook URL" class="m-0">
          <input v-model="draft.instagram" placeholder="Instagram URL" class="m-0">
          <input v-model="draft.whatsapp" placeholder="WhatsApp number" class="m-0">
        </div>
      </div>

      <!-- Tags -->
      <div class="mb-5">
        <label class="block mb-1.5">Tags</label>
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
        <input
          v-model="tagInput"
          placeholder="Type a tag and press Enter or comma"
          class="m-0"
          @keydown="onTagKey"
          @blur="addTag"
        >
      </div>

      <!-- Options -->
      <div class="mb-5 flex flex-col gap-3">
        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input v-model="draft.is_featured" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
          <span class="text-[.93rem] font-medium text-ink">Featured speaker</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer select-none">
          <input v-model="draft.is_public" type="checkbox" class="w-4.5 h-4.5 m-0 accent-brand">
          <span class="text-[.93rem] font-medium text-ink">Public profile</span>
        </label>
      </div>

      <p v-if="error" class="error mt-3">{{ error }}</p>

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button
          class="btn"
          :disabled="!draft.name.trim() || !draft.email.trim() || saving"
          @click="saveDraft"
        >
          {{ saving ? 'Saving…' : editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>

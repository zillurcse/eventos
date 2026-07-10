<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface Testimonial {
  id: string
  name: string
  role: string
  company: string
  quote: string
  rating: number
  avatar_file_id: number | null
  avatar_url: string | null
  featured: boolean
}

const testimonials = ref<Testimonial[]>([])
const drawerOpen = ref(false)
const editingId = ref<string | null>(null)
const saved = ref(false)

const draft = reactive<Testimonial>({
  id: '', name: '', role: '', company: '', quote: '',
  rating: 5, avatar_file_id: null, avatar_url: null, featured: false,
})

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

async function load() {
  try { testimonials.value = (await api<any>(`/events/${id}/settings`)).data.testimonials || [] } catch { /* */ }
}

async function persist() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { testimonials: JSON.parse(JSON.stringify(testimonials.value)) } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

function openAdd() {
  editingId.value = null
  Object.assign(draft, {
    id: 't' + Date.now(), name: '', role: '', company: '', quote: '',
    rating: 5, avatar_file_id: null, avatar_url: null, featured: false,
  })
  drawerOpen.value = true
}

function openEdit(t: Testimonial) {
  editingId.value = t.id
  Object.assign(draft, { ...t })
  drawerOpen.value = true
}

async function saveDraft() {
  if (!draft.name.trim() || !draft.quote.trim()) return
  const clean: Testimonial = JSON.parse(JSON.stringify(draft))
  clean.name = clean.name.trim()
  clean.quote = clean.quote.trim()
  if (editingId.value) {
    const i = testimonials.value.findIndex((t: Testimonial) => t.id === editingId.value)
    if (i >= 0) testimonials.value[i] = clean
  } else {
    testimonials.value.push(clean)
  }
  await persist()
  drawerOpen.value = false
}

async function removeTestimonial(t: Testimonial) {
  if (!confirm(`Remove testimonial from "${t.name}"?`)) return
  testimonials.value = testimonials.value.filter((x: Testimonial) => x.id !== t.id)
  await persist()
}

async function move(index: number, dir: -1 | 1) {
  const target = index + dir
  if (target < 0 || target >= testimonials.value.length) return
  const arr = testimonials.value
  ;[arr[index], arr[target]] = [arr[target], arr[index]]
  await persist()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Testimonials</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">Showcase quotes from past attendees, speakers and sponsors.</p>
    </div>

    <div class="card">
      <div class="flex items-center justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">
            Testimonials
            <span v-if="saved" class="badge active ml-2">saved ✓</span>
          </div>
          <div class="muted text-[.84rem]">Quotes shown on the event landing page.</div>
        </div>
        <button class="btn" @click="openAdd">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
          ADD TESTIMONIAL
        </button>
      </div>

      <div v-if="testimonials.length" class="flex flex-wrap gap-4">
        <div
          v-for="(t, i) in testimonials" :key="t.id"
          class="relative w-[300px] rounded-xl border border-line bg-white p-4 flex flex-col gap-3 shrink-0"
        >
          <span v-if="t.featured" class="absolute top-3 right-3 inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[.7rem] font-semibold">Featured</span>

          <!-- rating -->
          <div class="flex gap-0.5 text-[#f59e0b] text-sm">
            <span v-for="n in 5" :key="n">{{ n <= (t.rating || 0) ? '★' : '☆' }}</span>
          </div>

          <p class="text-[.9rem] text-ink leading-relaxed m-0 line-clamp-4">"{{ t.quote }}"</p>

          <div class="flex items-center gap-3 mt-auto pt-2">
            <div class="w-10 h-10 rounded-full overflow-hidden shrink-0 bg-brand-soft flex items-center justify-center text-brand font-semibold text-[.8rem]">
              <img v-if="t.avatar_url" :src="t.avatar_url" :alt="t.name" class="w-full h-full object-cover">
              <span v-else>{{ initials(t.name) }}</span>
            </div>
            <div class="min-w-0">
              <div class="font-semibold text-ink leading-tight truncate">{{ t.name }}</div>
              <div class="muted text-[.8rem] truncate">{{ [t.role, t.company].filter(Boolean).join(', ') || '—' }}</div>
            </div>
          </div>

          <!-- actions -->
          <div class="flex items-center gap-1 border-t border-line pt-2.5 -mb-1">
            <button class="icon-btn" title="Move up" :disabled="i === 0" @click="move(i, -1)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 15l-6-6-6 6"/></svg>
            </button>
            <button class="icon-btn" title="Move down" :disabled="i === testimonials.length - 1" @click="move(i, 1)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div class="flex-1" />
            <button class="icon-btn" title="Edit" @click="openEdit(t)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
            </button>
            <button class="icon-btn danger" title="Remove" @click="removeTestimonial(t)">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
          </div>
        </div>
      </div>

      <div v-else class="text-center py-13 px-5">
        <div class="w-13.5 h-13.5 rounded-[14px] bg-[#f3f0ff] text-[#6352e7] grid place-items-center mx-auto mb-3.5">
          <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        </div>
        <p class="muted m-0 mb-3">No testimonials yet. Add a quote from an attendee or sponsor.</p>
        <button class="btn" @click="openAdd">+ ADD TESTIMONIAL</button>
      </div>
    </div>

    <!-- Add / Edit drawer -->
    <Drawer
      v-if="drawerOpen"
      :title="editingId ? 'Edit Testimonial' : 'Add Testimonial'"
      @close="drawerOpen = false"
    >
      <div class="mb-5">
        <FormField label="Photo">
          <ImageField
            :model-value="draft.avatar_url"
            :aspect="1"
            :output-width="400"
            :output-height="400"
            collection="avatar"
            card-width="120px"
            hint="Square image recommended"
            :gallery-path="`/events/${id}/gallery`"
            @update:model-value="draft.avatar_url = (Array.isArray($event) ? $event[0] : $event) || null"
            @uploaded="(v: any) => draft.avatar_file_id = v.id"
          />
        </FormField>
      </div>

      <div class="mb-4">
        <AppInput v-model="draft.name" label="Name" required placeholder="Full name" />
      </div>

      <div class="mb-4 flex gap-3">
        <div class="flex-1">
          <AppInput v-model="draft.role" label="Role" placeholder="e.g. Attendee" />
        </div>
        <div class="flex-1">
          <AppInput v-model="draft.company" label="Company" placeholder="e.g. Acme Inc" />
        </div>
      </div>

      <div class="mb-4">
        <AppTextarea v-model="draft.quote" label="Quote" required :rows="5" placeholder="What did they say about your event?" />
      </div>

      <div class="mb-4">
        <FormField label="Rating">
          <div class="flex items-center gap-1 text-2xl">
            <button
              v-for="n in 5" :key="n"
              type="button"
              class="bg-transparent border-0 cursor-pointer p-0 leading-none"
              :class="n <= draft.rating ? 'text-[#f59e0b]' : 'text-[#d1d5db]'"
              @click="draft.rating = (draft.rating === n ? n - 1 : n)"
            >★</button>
          </div>
        </FormField>
      </div>

      <AppCheckbox v-model="draft.featured" label="Featured testimonial" description="Highlighted above other testimonials" />

      <div class="modal-actions border-t border-line pt-4 mt-5">
        <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
        <button class="btn" :disabled="!draft.name.trim() || !draft.quote.trim()" @click="saveDraft">
          {{ editingId ? 'UPDATE' : 'ADD' }}
        </button>
      </div>
    </Drawer>
  </div>
</template>

<style scoped>
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 8px;
  color: var(--muted, #6b7280);
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background .15s, color .15s;
}
.icon-btn:hover:not(:disabled) {
  background: #f3f0ff;
  color: #6352e7;
}
.icon-btn.danger:hover:not(:disabled) {
  background: #fef2f2;
  color: #dc2626;
}
.icon-btn:disabled {
  opacity: .35;
  cursor: not-allowed;
}
</style>

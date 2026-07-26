<script setup lang="ts">
export interface BrandingBanner {
  image:   string
  title?:  string
  url?:    string
  active?: boolean
}

const props = withDefaults(defineProps<{
  eventId:  string
  banners:  BrandingBanner[]
  title?:   string
  subtitle?: string
}>(), {
  title:    'Community Banner',
  subtitle: 'Banners displayed on the event landing page.',
})

const emit = defineEmits<{
  (e: 'update', v: BrandingBanner[]): void
}>()

const drawerOpen = ref(false)
const editIndex  = ref<number | null>(null)
const form       = reactive({ title: '', url: '', image: '' })
const formError  = ref('')

function openAdd() {
  editIndex.value = null
  Object.assign(form, { title: '', url: '', image: '' })
  formError.value = ''
  drawerOpen.value = true
}

function openEdit(i: number) {
  const b = props.banners[i]
  if (!b) return
  editIndex.value = i
  Object.assign(form, { title: b.title ?? '', url: b.url ?? '', image: b.image })
  formError.value = ''
  drawerOpen.value = true
}

function onImageChange(v: string | string[] | null) {
  form.image = Array.isArray(v) ? v[0] ?? '' : v ?? ''
}

// Per-card ImageField (single mode): null means Remove was clicked, otherwise
// it's a replaced/cropped image URL to swap in place.
function onCardImageChange(i: number, v: string | string[] | null) {
  if (v === null) {
    removeBanner(i)
    return
  }
  const url = Array.isArray(v) ? v[0] ?? '' : v
  const next = [...props.banners]
  next[i] = { ...next[i]!, image: url }
  emit('update', next)
}

function save() {
  if (!form.image) {
    formError.value = 'A banner image is required.'
    return
  }
  const item: BrandingBanner = {
    image:  form.image,
    title:  form.title.trim(),
    url:    form.url.trim(),
    active: editIndex.value === null ? true : props.banners[editIndex.value]?.active ?? true,
  }
  const next = [...props.banners]
  if (editIndex.value === null) next.push(item)
  else next[editIndex.value] = item
  emit('update', next)
  drawerOpen.value = false
}

function removeBanner(i: number) {
  const next = [...props.banners]
  next.splice(i, 1)
  emit('update', next)
}

function toggleActive(i: number) {
  emit('update', props.banners.map((b, j) => (j === i ? { ...b, active: !(b.active ?? true) } : b)))
}
</script>

<template>
  <div>
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1">
      <div>
        <h2 class="text-[1.05rem] font-bold text-ink mb-1">{{ title }}</h2>
        <p class="text-[.85rem] text-muted">{{ subtitle }}</p>
      </div>
      <button
        class="shrink-0 inline-flex items-center px-5 py-2.5 rounded-lg text-[.85rem] font-semibold bg-[#F0EEFD] text-brand-dark transition-colors hover:bg-brand hover:text-white cursor-pointer"
        @click="openAdd"
      >
        + Add banner
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!banners.length" class="flex flex-col items-center justify-center py-10 rounded-lg border border-dashed border-line bg-[#fafbfc] mt-4">
      <div class="w-10 h-10 rounded-lg bg-[#F0EEFD] grid place-items-center mb-3">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>
        </svg>
      </div>
      <p class="text-[.88rem] font-semibold text-ink mb-1">No {{ title }}s</p>
      <p class="text-[.82rem] text-muted mb-3">Add a banner to get started.</p>
      <button class="btn sm" @click="openAdd">Add banner</button>
    </div>

    <!-- Banner grid -->
    <div v-else class="grid grid-cols-2 gap-5 mt-4">
      <div v-for="(b, i) in banners" :key="b.image + i" :class="{ 'opacity-50': b.active === false }">
        <ImageField
          :model-value="b.image"
          :aspect="1036 / 350"
          :output-width="1036"
          :output-height="350"
          collection="banner"
          card-width="100%"
          :gallery-path="`/events/${eventId}/gallery`"
          @update:model-value="onCardImageChange(i, $event)"
        />
        <!-- <div class="flex items-center justify-between gap-3 mt-2">
          <div class="min-w-0">
            <p v-if="b.title" class="text-[.82rem] text-ink font-medium mb-0 truncate">{{ b.title }}</p>
            <p v-if="b.url" class="text-[.75rem] text-faint mt-0.5 mb-0 truncate">{{ b.url }}</p>
          </div>
          <div class="flex items-center gap-2 shrink-0">
            <button class="text-[.78rem] font-semibold text-brand cursor-pointer" @click="openEdit(i)">Edit details</button>
            <button
              class="text-[.78rem] font-semibold cursor-pointer"
              :class="b.active === false ? 'text-muted' : 'text-[#dc2626]'"
              @click="toggleActive(i)"
            >
              {{ b.active === false ? 'Show' : 'Hide' }}
            </button>
          </div>
        </div> -->
      </div>
    </div>

    <!-- Add / edit sidebar -->
    <Drawer v-if="drawerOpen" :title="`${editIndex === null ? 'Add' : 'Edit'} ${title}`" @close="drawerOpen = false">
      <div class="flex flex-col gap-4">
        <AppInput
          v-model="form.title"
          label="Title (optional)"
          placeholder="Banner title"
        />
        <AppInput
          v-model="form.url"
          label="Link URL (optional)"
          placeholder="https://…"
        />
        <div>
          <label class="block mb-2">Banner image</label>
          <ImageField
            :model-value="form.image || null"
            :aspect="1036 / 350"
            :output-width="1036"
            :output-height="350"
            collection="banner"
            hint="1036×350px recommended"
            card-width="100%"
            :gallery-path="`/events/${eventId}/gallery`"
            @update:model-value="onImageChange"
          />
        </div>
        <p v-if="formError" class="error mb-0">{{ formError }}</p>
        <div class="flex justify-end gap-2.5 mt-2">
          <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
          <button class="btn" @click="save">{{ editIndex === null ? 'Add banner' : 'Save changes' }}</button>
        </div>
      </div>
    </Drawer>
  </div>
</template>

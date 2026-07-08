<script setup lang="ts">
export interface BrandingBanner {
  image:   string
  title?:  string
  url?:    string
  active?: boolean
}

const props = defineProps<{
  eventId: string
  banners: BrandingBanner[]
}>()

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
  <div class="card">
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1.5">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>
          </svg>
        </div>
        <div>
          <h2 class="mb-0!">Community Banner</h2>
          <p class="text-[.8rem] text-muted mt-0.5">Banners displayed on the event landing page.</p>
        </div>
      </div>
      <button class="btn sm ghost shrink-0" @click="openAdd">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        Add banner
      </button>
    </div>

    <!-- Empty state -->
    <div v-if="!banners.length" class="flex flex-col items-center justify-center py-10 rounded-xl border border-dashed border-line bg-[#fafbfc] mt-4">
      <div class="w-10 h-10 rounded-xl bg-brand-soft grid place-items-center mb-3">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><path d="M4 22v-7"/>
        </svg>
      </div>
      <p class="text-[.88rem] font-semibold text-ink mb-1">No Community Banners</p>
      <p class="text-[.82rem] text-muted mb-3">Add a banner to get started.</p>
      <button class="btn sm" @click="openAdd">Add banner</button>
    </div>

    <!-- Banner grid -->
    <div v-else class="flex gap-4 flex-wrap mt-4">
      <div v-for="(b, i) in banners" :key="b.image + i" class="w-75">
        <div class="img-card" :class="{ 'opacity-50': b.active === false }" :style="{ aspectRatio: '1036 / 350' }">
          <img :src="b.image" :alt="b.title || 'Community banner'">
          <span v-if="b.active === false" class="badge draft absolute top-1.5 left-1.5">Hidden</span>
          <div class="img-card-actions">
            <button class="img-action" title="Edit banner" @click="openEdit(i)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.85 0 114 4L7.5 20.5 2 22l1.5-5.5z"/></svg>
            </button>
            <button class="img-action" :title="b.active === false ? 'Show banner' : 'Hide banner'" @click="toggleActive(i)">
              <svg v-if="b.active === false" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M1 1l22 22"/></svg>
              <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="img-action danger" title="Remove banner" @click="removeBanner(i)">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
        <p v-if="b.title" class="text-[.82rem] text-ink font-medium mt-1.5 mb-0 truncate">{{ b.title }}</p>
        <p v-if="b.url" class="text-[.75rem] text-faint mt-0.5 mb-0 truncate">{{ b.url }}</p>
      </div>
    </div>

    <!-- Add / edit sidebar -->
    <Drawer v-if="drawerOpen" :title="editIndex === null ? 'Add Community Banner' : 'Edit Community Banner'" @close="drawerOpen = false">
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

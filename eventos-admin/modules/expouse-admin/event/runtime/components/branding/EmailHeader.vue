<script setup lang="ts">
const props = defineProps<{
  emailHeaderUrl: string | null
}>()

const emit = defineEmits<{
  (e: 'uploaded', v: { url: string | null }): void
}>()

const drawerOpen = ref(false)
const draft = ref<string | null>(null)

function openDrawer() {
  draft.value = props.emailHeaderUrl
  drawerOpen.value = true
}

function onImageChange(v: string | string[] | null) {
  draft.value = Array.isArray(v) ? v[0] ?? null : v
}

function save() {
  emit('uploaded', { url: draft.value })
  drawerOpen.value = false
}
</script>

<template>
  <div class="card">
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1.5">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <path d="M17 20.5H7c-3 0-5-1.5-5-5v-7c0-3.5 2-5 5-5h10c3 0 5 1.5 5 5v7c0 3.5-2 5-5 5z"/>
            <path d="m17 9-3.13 2.5c-1.03.82-2.72.82-3.75 0L7 9"/>
          </svg>
        </div>
        <div>
          <h2 class="mb-0!">Email Header</h2>
          <p class="text-[.8rem] text-muted mt-0.5">Header image shown at the top of all event emails.</p>
        </div>
      </div>
      <button class="btn sm ghost shrink-0" @click="openDrawer">
        {{ emailHeaderUrl ? 'Edit header' : 'Add header' }}
      </button>
    </div>

    <!-- Preview -->
    <div v-if="emailHeaderUrl" class="mt-4 rounded-xl overflow-hidden border border-line max-w-110" :style="{ aspectRatio: '4' }">
      <img :src="emailHeaderUrl" alt="Email header" class="w-full h-full object-cover">
    </div>
    <div v-else class="flex items-center justify-center py-8 rounded-xl border border-dashed border-line bg-[#fafbfc] mt-4 text-[.85rem] text-muted">
      No email header yet.
    </div>

    <!-- Edit sidebar -->
    <Drawer v-if="drawerOpen" title="Email Header" @close="drawerOpen = false">
      <div class="flex flex-col gap-4">
        <div>
          <label class="block mb-2">Header image</label>
          <ImageField
            :model-value="draft"
            :aspect="4"
            :output-width="1200"
            :output-height="300"
            collection="email_header"
            hint="1200×300px recommended"
            card-width="100%"
            @update:model-value="onImageChange"
          />
        </div>
        <div class="flex justify-end gap-2.5 mt-2">
          <button class="btn ghost" @click="drawerOpen = false">Cancel</button>
          <button class="btn" @click="save">Save changes</button>
        </div>
      </div>
    </Drawer>
  </div>
</template>

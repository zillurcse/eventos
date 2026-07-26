<script setup lang="ts">
const props = defineProps<{
  eventId: string
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
  <div>
    <!-- Section header -->
    <div class="flex items-start justify-between gap-4 mb-1">
      <div>
        <h2 class="text-[1.05rem] font-bold text-ink mb-1">Email Header</h2>
        <p class="text-[.85rem] text-muted">Header image shown at the top of all event emails.</p>
      </div>
      <button
        class="shrink-0 inline-flex items-center px-5 py-2.5 rounded-lg text-[.85rem] font-semibold bg-[#F0EEFD] text-brand-dark transition-colors hover:bg-brand hover:text-white cursor-pointer"
        @click="openDrawer"
      >
       + {{ emailHeaderUrl ? 'Edit header' : 'Add header' }}
      </button>
    </div>

    <!-- Preview -->
    <div v-if="emailHeaderUrl" class="mt-4 rounded-lg overflow-hidden border border-line max-w-110" :style="{ aspectRatio: '4' }">
      <img :src="emailHeaderUrl" alt="Email header" class="w-full h-full object-cover">
    </div>
    <div v-else class="flex items-center justify-center py-8 rounded-lg border border-dashed border-line bg-[#fafbfc] mt-4 text-[.85rem] text-muted">
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
            :gallery-path="`/events/${eventId}/gallery`"
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

<script setup lang="ts">
const props = defineProps<{
  tabs: { items: { key: string; label: string; enabled: boolean }[] }
}>()

const emit = defineEmits<{
  (e: 'save'): void
}>()

const open = ref(false)
</script>

<template>
  <!-- Section row -->
  <div class="px-6 py-6">
    <div class="flex items-start justify-between gap-4">
      <div>
        <h2 class="text-[1.05rem] font-bold text-ink mb-1">Allowed feed tabs</h2>
        <p class="text-[.85rem] text-muted">Choose the tabs you want to be displayed on the feed page</p>
      </div>
      <button
        class="shrink-0 inline-flex items-center px-5 py-2.5 rounded-lg text-[.85rem] font-semibold bg-[#F0EEFD] text-brand-dark transition-colors hover:bg-brand hover:text-white cursor-pointer"
        @click="open = true"
      >
        Manage
      </button>
    </div>
  </div>

  <!-- Drawer -->
  <Drawer v-if="open" title="Feed Tabs" @close="open = false">
    <SortableList v-model="tabs.items" />
    <div class="modal-actions">
      <button class="btn ghost" @click="open = false">Cancel</button>
      <button class="btn" @click="emit('save'); open = false">Save</button>
    </div>
  </Drawer>
</template>

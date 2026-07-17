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
  <div class="px-5 py-5">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-[.95rem] text-ink mb-0.5">Allowed Feed Tabs</p>
        <p class="text-[.82rem] text-muted">Choose the content tabs visible on the event feed page.</p>
      </div>
      <button class="btn ghost shrink-0" @click="open = true">
        Manage
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
    </div>

    <!-- Inline preview pills -->
    <div v-if="tabs.items.length" class="flex gap-1.5 flex-wrap mt-3 pt-3 border-t border-line">
      <span
        v-for="item in tabs.items.slice(0, 8)" :key="item.key"
        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[.78rem] font-medium"
        :class="item.enabled ? 'bg-brand-soft text-brand' : 'bg-faint text-muted line-through'"
      >
        {{ item.label }}
      </span>
      <span v-if="tabs.items.length > 8" class="inline-flex items-center px-2.5 py-1 rounded-lg text-[.78rem] text-muted bg-faint">
        +{{ tabs.items.length - 8 }} more
      </span>
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

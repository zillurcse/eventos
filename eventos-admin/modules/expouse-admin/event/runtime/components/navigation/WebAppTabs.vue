<script setup lang="ts">
const ALIGN = [
  { v: 'left',    d: 'M3 6h18M3 12h12M3 18h15' },
  { v: 'center',  d: 'M3 6h18M6 12h12M5 18h14' },
  { v: 'right',   d: 'M3 6h18M9 12h12M6 18h15' },
  { v: 'justify', d: 'M3 6h18M3 12h18M3 18h18' },
]

const props = defineProps<{
  tabs: {
    items:      { key: string; label: string; enabled: boolean }[]
    icons:      boolean
    background: boolean
    alignment:  string
  }
}>()

const emit = defineEmits<{
  (e: 'save'): void
}>()

const open = ref(false)
</script>

<template>
  <!-- Section row -->
  <div class="card">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-[.95rem] text-ink mb-0.5">Web App Tabs</p>
        <p class="text-[.82rem] text-muted">Personalise the sections and order shown in your web app.</p>
      </div>
      <button class="btn ghost shrink-0" @click="open = true">
        Manage
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
    </div>

    <!-- Inline preview pills — enabled tabs only -->
    <div class="flex gap-1.5 flex-wrap mt-3 pt-3 border-t border-line">
      <template v-if="tabs.items.filter(i => i.enabled).length">
        <span
          v-for="item in tabs.items.filter(i => i.enabled).slice(0, 8)" :key="item.key"
          class="inline-flex items-center px-2.5 py-1 rounded-lg text-[.78rem] font-medium bg-brand-soft text-brand"
        >
          {{ item.label }}
        </span>
        <span v-if="tabs.items.filter(i => i.enabled).length > 8" class="inline-flex items-center px-2.5 py-1 rounded-lg text-[.78rem] text-muted bg-faint">
          +{{ tabs.items.filter(i => i.enabled).length - 8 }} more
        </span>
      </template>
      <span v-else class="text-[.82rem] text-muted">No tabs enabled</span>
    </div>
  </div>

  <!-- Drawer -->
  <Drawer v-if="open" title="Web App Tabs" @close="open = false">
    <!-- Controls row -->
    <div class="flex items-center gap-4 mb-5 flex-wrap">
      <button
        type="button"
        class="inline-flex items-center gap-2.5 bg-transparent border-0 cursor-pointer font-semibold text-[.9rem] p-0 group"
        :class="tabs.icons ? 'text-brand' : 'text-muted'"
        @click="tabs.icons = !tabs.icons"
      >
        <span
          class="relative w-10 h-[22px] rounded-full shrink-0 transition-colors duration-150"
          :class="tabs.icons ? 'bg-brand' : 'bg-[#cdd2dc]'"
        >
          <i
            class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-150 shadow-sm"
            :class="tabs.icons ? 'translate-x-[18px]' : ''"
          />
        </span>
        Icons
      </button>

      <button
        type="button"
        class="inline-flex items-center gap-2.5 bg-transparent border-0 cursor-pointer font-semibold text-[.9rem] p-0 group"
        :class="tabs.background ? 'text-brand' : 'text-muted'"
        @click="tabs.background = !tabs.background"
      >
        <span
          class="relative w-10 h-[22px] rounded-full shrink-0 transition-colors duration-150"
          :class="tabs.background ? 'bg-brand' : 'bg-[#cdd2dc]'"
        >
          <i
            class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-150 shadow-sm"
            :class="tabs.background ? 'translate-x-[18px]' : ''"
          />
        </span>
        Background
      </button>

      <div class="flex-1" />

      <!-- Alignment -->
      <div class="inline-flex gap-1">
        <button
          v-for="a in ALIGN" :key="a.v"
          type="button"
          class="w-9 h-9 rounded-lg border border-line bg-white grid place-items-center cursor-pointer transition-all duration-150"
          :class="tabs.alignment === a.v
            ? 'bg-brand-soft border-brand text-brand'
            : 'text-muted hover:text-brand hover:border-[#c7c2f5]'"
          :title="a.v"
          @click="tabs.alignment = a.v"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
            <path :d="a.d" />
          </svg>
        </button>
      </div>
    </div>

    <SortableList v-model="tabs.items" editable />

    <div class="modal-actions">
      <button class="btn ghost" @click="open = false">Cancel</button>
      <button class="btn" @click="emit('save'); open = false">Save</button>
    </div>
  </Drawer>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

interface SpeakerRow {
  id: string
  name: string
  email: string
  designation: string
  company: string
  category: string
  image_url: string | null
  has_login: boolean
}

defineProps<{
  speakers: SpeakerRow[]
  searching?: boolean
}>()

const emit = defineEmits<{
  (e: 'edit', s: SpeakerRow): void
  (e: 'remove', s: SpeakerRow): void
  (e: 'login', s: SpeakerRow): void
  (e: 'ratings', s: SpeakerRow): void
}>()

const columns = [
  { key: 'image', label: 'Image', width: '68px' },
  { key: 'name', label: 'Name' },
  { key: 'email', label: 'Email' },
  { key: 'designation', label: 'Designation' },
  { key: 'company', label: 'Company' },
]

function initials(name: string): string {
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

const actionsFor = ref<string | null>(null)
// The actions menu is teleported to <body> (DataTable clips overflow), so track
// the trigger button's viewport position to anchor the menu there.
const actionsAnchor = ref<{ top: number; right: number } | null>(null)

function toggleActions(id: string, ev: MouseEvent) {
  if (actionsFor.value === id) {
    closeActions()
    return
  }
  actionsFor.value = id
  const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
  actionsAnchor.value = { top: rect.bottom + 4, right: window.innerWidth - rect.right }
}
function closeActions() {
  actionsFor.value = null
  actionsAnchor.value = null
}

// Fixed-position teleported menu lives outside the table's own DOM, so close
// it on any window click/scroll rather than relying on an overlay div.
const onWindowClick = () => closeActions()
const onWindowScroll = () => closeActions()
onMounted(() => {
  window.addEventListener('click', onWindowClick)
  window.addEventListener('scroll', onWindowScroll, true)
})
onBeforeUnmount(() => {
  window.removeEventListener('click', onWindowClick)
  window.removeEventListener('scroll', onWindowScroll, true)
})
</script>

<template>
  <DataTable
    :items="speakers"
    :columns="columns"
    row-key="id"
    storage-key="speakers"
    :empty-text="searching ? 'No speakers match your search.' : 'No speakers yet. Click + SPEAKERS to add one.'"
  >
    <template #cell-image="{ row }">
        <div class="min-h-[47px] flex items-center">
          <div class="w-14 h-10 rounded-lg overflow-hidden shrink-0 bg-[#e8effb] flex items-center justify-center text-[#5b8def] font-semibold text-[.8rem]">
            <img v-if="row.image_url" :src="row.image_url" :alt="row.name" class="w-full h-full object-cover">
            <span v-else>{{ initials(row.name) }}</span>
          </div>
        </div>
    </template>

    <template #cell-name="{ row }">
      <button
        class="bg-transparent border-0 p-0 cursor-pointer text-ink font-medium text-[.92rem] text-left"
        @click="emit('edit', row)"
      >{{ row.name }}</button>
      <span v-if="row.category" class="block mt-0.5 text-muted text-[.75rem]">{{ row.category }}</span>
    </template>

    <template #cell-email="{ row }">
      <span class="text-muted text-[.88rem]">{{ row.email }}</span>
      <!-- A speaker with no login can't sign in to the event site, which means
           they can't take the stage on their own session. -->
      <span
        v-if="!row.has_login"
        class="block mt-0.5 text-[.7rem] font-semibold uppercase tracking-wide text-[#b45309]"
      >No login</span>
    </template>

    <template #cell-designation="{ row }">
      <span class="text-ink text-[.88rem]">{{ row.designation || '—' }}</span>
    </template>

    <template #cell-company="{ row }">
      <span class="text-ink text-[.88rem]">{{ row.company || '—' }}</span>
    </template>

    <template #actions="{ row }">
      <div class="relative inline-block" @click.stop>
        <button class="w-8 h-8 rounded-lg grid place-items-center text-muted hover:bg-[#f1f2f6] border-0 bg-transparent cursor-pointer" aria-label="Actions" @click="toggleActions(row.id, $event)">
          <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
        </button>
        <Teleport to="body">
          <div
            v-if="actionsFor === row.id && actionsAnchor"
            class="fixed bg-white rounded-xl border border-[#E8E8EE] shadow-xl z-30 min-w-40 overflow-hidden p-2"
            :style="{ top: `${actionsAnchor.top}px`, right: `${actionsAnchor.right}px` }"
            @click.stop
          >
            <button
              class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-brand hover:bg-[#F7F7FB] cursor-pointer bg-transparent border-0 text-left transition-colors"
              @click="closeActions(); emit('edit', row)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 15 15" fill="none">
                <path d="M2.25 12.0043H5.43C5.5287 12.0049 5.62655 11.986 5.71793 11.9487C5.80931 11.9114 5.89242 11.8564 5.9625 11.7868L11.1525 6.58935L13.2825 4.50435C13.3528 4.43463 13.4086 4.35168 13.4467 4.26028C13.4847 4.16889 13.5043 4.07086 13.5043 3.97185C13.5043 3.87284 13.4847 3.77481 13.4467 3.68342C13.4086 3.59202 13.3528 3.50907 13.2825 3.43935L10.1025 0.221849C10.0328 0.151552 9.94983 0.0957567 9.85843 0.0576802C9.76704 0.0196037 9.66901 0 9.57 0C9.47099 0 9.37296 0.0196037 9.28157 0.0576802C9.19017 0.0957567 9.10722 0.151552 9.0375 0.221849L6.9225 2.34435L1.7175 7.54185C1.64799 7.61193 1.593 7.69504 1.55567 7.78642C1.51835 7.8778 1.49943 7.97564 1.5 8.07435V11.2543C1.5 11.4533 1.57902 11.644 1.71967 11.7847C1.86032 11.9253 2.05109 12.0043 2.25 12.0043ZM9.57 1.81185L11.6925 3.93435L10.6275 4.99935L8.505 2.87685L9.57 1.81185ZM3 8.38185L7.4475 3.93435L9.57 6.05685L5.1225 10.5043H3V8.38185ZM14.25 13.5043H0.75C0.551088 13.5043 0.360322 13.5834 0.21967 13.724C0.0790176 13.8647 0 14.0554 0 14.2543C0 14.4533 0.0790176 14.644 0.21967 14.7847C0.360322 14.9253 0.551088 15.0043 0.75 15.0043H14.25C14.4489 15.0043 14.6397 14.9253 14.7803 14.7847C14.921 14.644 15 14.4533 15 14.2543C15 14.0554 14.921 13.8647 14.7803 13.724C14.6397 13.5834 14.4489 13.5043 14.25 13.5043Z" fill="#6452E7"/>
              </svg>
              Edit
            </button>
            <button
              class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left transition-colors"
              :class="row.has_login ? 'text-ink' : 'text-[#b45309]'"
              @click="closeActions(); emit('login', row)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              {{ row.has_login ? 'Reset Password' : 'Create Login' }}
            </button>
            <button
              class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left transition-colors"
              @click="closeActions(); emit('ratings', row)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.4 5.9 20.6l1.4-6.8L2.2 9.1l6.9-.8z"/></svg>
              View Ratings
            </button>
            <button
              class="w-full flex items-center gap-3 px-3.5 py-2.5 rounded-lg max-h-10 text-[.92rem] font-medium text-ink hover:bg-[#f7f8fa] cursor-pointer bg-transparent border-0 text-left transition-colors"
              @click="closeActions(); emit('remove', row)"
            >
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>
              Delete
            </button>
          </div>
        </Teleport>
      </div>
    </template>
  </DataTable>
</template>

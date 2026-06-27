<script setup lang="ts">
/** Dropdown that lists the dynamic merge-variable catalogue and emits the chosen
 *  token (e.g. "contact.first_name") for insertion into the active field. */
export interface VarGroup {
  group: string
  label: string
  variables: { token: string, label: string, sample: string }[]
}

defineProps<{ groups: VarGroup[], compact?: boolean }>()
const emit = defineEmits<{ (e: 'insert', token: string): void }>()

const open = ref(false)
const q = ref('')
const root = ref<HTMLElement | null>(null)

function onDoc(e: MouseEvent) {
  if (root.value && !root.value.contains(e.target as Node)) open.value = false
}
onMounted(() => document.addEventListener('mousedown', onDoc))
onBeforeUnmount(() => document.removeEventListener('mousedown', onDoc))

function pick(token: string) {
  emit('insert', token)
  open.value = false
  q.value = ''
}
// built in script so the literal "}}" never reaches Vue's mustache parser
const BRACES = '{ }'
function tokenDisplay(token: string) { return '{{ ' + token + ' }}' }
</script>

<template>
  <div ref="root" class="relative inline-block">
    <button
      type="button"
      class="inline-flex items-center gap-1 rounded-md border border-line bg-white text-[#6352e7] font-semibold cursor-pointer hover:bg-[#f5f3ff]"
      :class="compact ? 'text-[.72rem] px-1.5 py-1' : 'text-[.8rem] px-2.5 py-1.5'"
      title="Insert dynamic variable"
      @click="open = !open"
    >
      <span class="font-mono">{{ BRACES }}</span>
      <span v-if="!compact">Variable</span>
    </button>

    <div v-if="open" class="absolute right-0 z-[60] mt-1 w-[260px] max-h-[340px] overflow-y-auto rounded-xl border border-line bg-white shadow-lg p-2">
      <input
        v-model="q"
        placeholder="Search variables…"
        class="m-0 mb-2 w-full text-[.82rem] py-1.5 px-2"
        @keydown.stop
      >
      <div v-for="g in groups" :key="g.group" class="mb-1.5">
        <template v-for="v in g.variables.filter(x => (x.label + x.token).toLowerCase().includes(q.toLowerCase()))" :key="v.token">
          <button
            type="button"
            class="w-full text-left rounded-md px-2 py-1.5 hover:bg-[#f5f3ff] cursor-pointer block"
            @click="pick(v.token)"
          >
            <span class="block text-[.82rem] font-medium text-[#1f2430]">{{ v.label }}</span>
            <span class="block text-[.7rem] font-mono text-[#8b93a7]">{{ tokenDisplay(v.token) }}</span>
          </button>
        </template>
      </div>
      <div v-if="!groups.length" class="text-[.78rem] text-[#8b93a7] text-center py-3">Loading…</div>
    </div>
  </div>
</template>

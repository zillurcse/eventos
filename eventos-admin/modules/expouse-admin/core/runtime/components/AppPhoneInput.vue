<script setup lang="ts">
import { PHONE_CODES, findPhoneCode, type PhoneCode } from '../utils/phoneCodes'

const props = defineProps<{
  phoneCode?:  string | null
  phone?:      string | null
  label?:      string
  placeholder?: string
  required?:   boolean
  error?:      string
  hint?:       string
}>()

const emit = defineEmits<{
  (e: 'update:phoneCode', v: string): void
  (e: 'update:phone', v: string): void
}>()

const showRequiredMark = computed(() =>
  !!props.required && !props.error && !(props.phone ?? '').trim(),
)

// ── Country code picker ──────────────────────────────────────────────────
const open = ref(false)
const query = ref('')
const rootEl = ref<HTMLElement | null>(null)
const searchEl = ref<HTMLInputElement | null>(null)
const activeIdx = ref(-1)
const pickedIso = ref<string | null>(null)

const selected = computed(() => {
  const code = props.phoneCode
  if (pickedIso.value && code) {
    const match = PHONE_CODES.find(p => p.iso === pickedIso.value && p.code === code)
    if (match) return match
  }
  return findPhoneCode(code) ?? PHONE_CODES.find(p => p.iso === 'BD')!
})

watch(() => props.phoneCode, (code) => {
  const stillValid = !!code && PHONE_CODES.some(p => p.iso === pickedIso.value && p.code === code)
  if (!stillValid) pickedIso.value = findPhoneCode(code)?.iso ?? null
}, { immediate: true })

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase().replace(/\s+/g, ' ')
  if (!q) return PHONE_CODES
  return PHONE_CODES.filter(p =>
    p.name.toLowerCase().includes(q)
    || p.code.includes(q)
    || p.code.replace('+', '').includes(q.replace('+', ''))
    || p.iso.toLowerCase().includes(q),
  )
})

watch(filtered, () => { activeIdx.value = -1 })

function openList() {
  if (open.value) return
  open.value = true
  query.value = ''
  activeIdx.value = -1
  nextTick(() => searchEl.value?.focus())
}

function closeList() {
  open.value = false
  query.value = ''
  activeIdx.value = -1
}

function pick(p: PhoneCode) {
  pickedIso.value = p.iso
  emit('update:phoneCode', p.code)
  closeList()
}

function onSearchKey(e: KeyboardEvent) {
  const list = filtered.value
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (!open.value) { openList(); return }
    activeIdx.value = Math.min(activeIdx.value + 1, list.length - 1)
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIdx.value = Math.max(activeIdx.value - 1, 0)
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (activeIdx.value >= 0 && list[activeIdx.value]) pick(list[activeIdx.value])
    else if (list.length === 1) pick(list[0])
  } else if (e.key === 'Escape') {
    e.preventDefault()
    closeList()
  }
}

function onDocPointer(e: Event) {
  if (!open.value || !rootEl.value) return
  if (!rootEl.value.contains(e.target as Node)) closeList()
}

onMounted(() => document.addEventListener('pointerdown', onDocPointer))
onBeforeUnmount(() => document.removeEventListener('pointerdown', onDocPointer))
</script>

<template>
  <div>
    <label v-if="label" class="block mb-1.5">{{ label }}</label>
    <div
      class="flex items-stretch h-12 rounded-lg overflow-visible border border-[#d7dae1] bg-white transition-colors focus-within:border-brand"
      :class="[
        error ? '!border-[#dc2626]' : '',
        showRequiredMark ? '!border-l-[3px] !border-l-[#e11d48]' : '',
      ]"
    >
      <!-- Country code -->
      <div ref="rootEl" class="relative shrink-0 h-full">
        <button
          type="button"
          class="h-full px-3 border-0 border-r border-[#e7e9ee] bg-[#f7f8fa] cursor-pointer flex items-center gap-2 text-[.93rem] text-ink whitespace-nowrap rounded-l-lg"
          :aria-expanded="open"
          aria-haspopup="listbox"
          @click="openList"
        >
          <span class="w-7 h-7 rounded-full overflow-hidden grid place-items-center bg-white shrink-0 text-[1.15rem] leading-none shadow-[inset_0_0_0_1px_#e7e9ee]">
            {{ selected.flag }}
          </span>
          <span class="font-medium text-ink">{{ selected.code }}</span>
          <svg
            class="w-3.5 h-3.5 text-[#9aa1ad] transition-transform shrink-0"
            :class="open ? 'rotate-180' : ''"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
          >
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
          </svg>
        </button>

        <div
          v-if="open"
          class="absolute z-[100] left-0 top-full mt-1 w-72 bg-white border border-[#d7dae1] rounded-lg shadow-lg overflow-hidden"
        >
          <div class="p-2 border-b border-line">
            <input
              ref="searchEl"
              v-model="query"
              type="text"
              placeholder="Search country or code"
              autocomplete="off"
              style="border:0;box-shadow:none;margin:0;padding:8px 10px;border-radius:8px;height:auto;width:100%;outline:none;background:#f7f8fa;font-size:.9rem;"
              @keydown="onSearchKey"
            >
          </div>
          <ul class="max-h-56 overflow-auto py-1 m-0 list-none" role="listbox">
            <li
              v-for="(p, i) in filtered"
              :key="p.iso"
              role="option"
              class="px-3 py-2.5 text-[.9rem] cursor-pointer flex items-center gap-2.5"
              :class="[
                p.iso === selected.iso ? 'bg-[#F0EEFD] text-brand-dark font-medium' : 'text-ink',
                i === activeIdx ? 'bg-[#f7f8fa]' : 'hover:bg-[#f7f8fa]',
              ]"
              @mousedown.prevent="pick(p)"
              @mouseenter="activeIdx = i"
            >
              <span class="w-6 h-6 rounded-full overflow-hidden grid place-items-center bg-white text-base leading-none shadow-[inset_0_0_0_1px_#e7e9ee]">
                {{ p.flag }}
              </span>
              <span class="flex-1 truncate">{{ p.name }}</span>
              <span class="text-muted shrink-0">{{ p.code }}</span>
            </li>
            <li v-if="!filtered.length" class="px-3 py-2.5 text-[.9rem] text-muted">No matches</li>
          </ul>
        </div>
      </div>

      <!-- Number -->
      <input
        type="tel"
        :value="phone ?? ''"
        :placeholder="placeholder ?? 'Enter Mobile No'"
        class="!w-auto !my-0 flex-1 min-w-0 bg-white text-[.93rem] text-ink placeholder:text-[#9aa1ad] rounded-r-lg"
        style="border:0;box-shadow:none;margin:0;border-radius:0;outline:none;height:48px;padding:0 13px;"
        @input="emit('update:phone', ($event.target as HTMLInputElement).value)"
      >
    </div>
    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>

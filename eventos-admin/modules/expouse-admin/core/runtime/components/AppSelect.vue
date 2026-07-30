<script setup lang="ts">
export interface SelectOption {
  value: string | number
  label: string
}

const props = defineProps<{
  modelValue?:  string | number | null
  label?:       string
  options?:     SelectOption[] | string[]
  placeholder?: string
  required?:    boolean
  error?:       string
  hint?:        string
  disabled?:    boolean
  /** Type-to-filter combobox instead of a native <select>. */
  searchable?:  boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: string | number): void
}>()

function normalised(): SelectOption[] {
  if (!props.options) return []
  return props.options.map(o =>
    typeof o === 'string' ? { value: o, label: o } : o,
  )
}

function onChange(e: Event) {
  emit('update:modelValue', (e.target as HTMLSelectElement).value)
}

// ── Searchable combobox ──────────────────────────────────────────────────
const open = ref(false)
const query = ref('')
const rootEl = ref<HTMLElement | null>(null)
const inputEl = ref<HTMLInputElement | null>(null)
const activeIdx = ref(-1)

const selectedLabel = computed(() => {
  if (props.modelValue == null || props.modelValue === '') return ''
  return normalised().find(o => String(o.value) === String(props.modelValue))?.label
    ?? String(props.modelValue)
})

/** Shown in the field: live search text while open, otherwise the selected label. */
const displayValue = computed(() => open.value ? query.value : selectedLabel.value)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  const opts = normalised()
  if (!q) return opts
  return opts.filter(o => o.label.toLowerCase().includes(q))
})

watch(filtered, () => { activeIdx.value = -1 })

function openList() {
  if (props.disabled || open.value) return
  open.value = true
  query.value = ''
  activeIdx.value = -1
  nextTick(() => inputEl.value?.focus())
}

function closeList() {
  open.value = false
  query.value = ''
  activeIdx.value = -1
}

function pick(opt: SelectOption) {
  emit('update:modelValue', opt.value)
  closeList()
}

function clearSelection(e: Event) {
  e.stopPropagation()
  if (props.disabled) return
  emit('update:modelValue', '')
  query.value = ''
  open.value = true
  nextTick(() => inputEl.value?.focus())
}

function onInput(e: Event) {
  query.value = (e.target as HTMLInputElement).value
  if (!open.value) openList()
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
onBeforeUnmount(() => {
  closeList()
  document.removeEventListener('pointerdown', onDocPointer)
})

/** Red left bar only while required and still empty. */
const showRequiredMark = computed(() => {
  if (!props.required || props.error) return false
  const v = props.modelValue
  return v == null || v === ''
})
</script>

<template>
  <div ref="rootEl" class="relative">
    <label v-if="label" class="block mb-1.5">{{ label }}</label>

    <!-- Native select (default) -->
    <div
      v-if="!searchable"
      class="w-full bg-white border rounded-lg h-12 px-3"
      :class="[
        error ? 'border-[#dc2626]' : 'border-[#d7dae1]',
        showRequiredMark ? '!border-l-[3px] !border-l-[#e11d48]' : '',
      ]"
    >
      <select
        :value="modelValue ?? ''"
        :disabled="disabled"
        :required="required"
        class="m-0 w-full border-0 focus:outline-0 h-full"
        @change="onChange"
      >
        <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
        <option
          v-for="opt in normalised()"
          :key="opt.value"
          :value="opt.value"
        >{{ opt.label }}</option>
      </select>
    </div>

    <!-- Searchable combobox — input resets global form styles via inline CSS -->
    <div
      v-else
      class="w-full bg-white border rounded-lg h-12 px-3 flex items-center gap-1.5"
      :class="[
        error ? 'border-[#dc2626]' : open ? 'border-brand' : 'border-[#d7dae1]',
        showRequiredMark ? '!border-l-[3px] !border-l-[#e11d48]' : '',
        disabled ? 'opacity-60 pointer-events-none' : 'cursor-text',
      ]"
      @click="openList"
    >
      <input
        ref="inputEl"
        type="text"
        :value="displayValue"
        :placeholder="placeholder || 'Search…'"
        :disabled="disabled"
        autocomplete="off"
        style="border:0;box-shadow:none;margin:0;padding:0;border-radius:0;height:auto;width:auto;flex:1;min-width:0;outline:none;background:transparent;font-size:.93rem;"
        @input="onInput"
        @focus="openList"
        @keydown="onSearchKey"
      >
      <button
        v-if="selectedLabel && !disabled"
        type="button"
        class="shrink-0 border-0 bg-transparent p-0 w-5 h-5 grid place-items-center text-muted hover:text-ink cursor-pointer leading-none text-base"
        aria-label="Clear"
        @click="clearSelection"
      >×</button>
      <svg
        class="w-4 h-4 shrink-0 text-muted transition-transform pointer-events-none"
        :class="open ? 'rotate-180' : ''"
        viewBox="0 0 20 20"
        fill="currentColor"
        aria-hidden="true"
      >
        <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
      </svg>
    </div>

    <ul
      v-if="searchable && open"
      class="absolute z-50 left-0 right-0 top-full mt-1 max-h-60 overflow-auto bg-white border border-[#d7dae1] rounded-lg shadow-lg py-1 m-0 list-none"
      role="listbox"
    >
      <li
        v-for="(opt, i) in filtered"
        :key="opt.value"
        role="option"
        class="px-3 py-2.5 text-[.93rem] cursor-pointer"
        :class="[
          String(opt.value) === String(modelValue) ? 'bg-[#F0EEFD] text-brand-dark font-medium' : 'text-ink',
          i === activeIdx ? 'bg-[#f7f8fa]' : 'hover:bg-[#f7f8fa]',
        ]"
        @mousedown.prevent="pick(opt)"
        @mouseenter="activeIdx = i"
      >{{ opt.label }}</li>
      <li v-if="!filtered.length" class="px-3 py-2.5 text-[.93rem] text-muted">No matches</li>
    </ul>

    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>

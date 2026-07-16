<script setup lang="ts">
/** Single-select combobox with an in-panel search box — used for the
 *  Language and Time Zone fields on Profile › Language & Time zone, where a
 *  plain <select> would make picking a zone by scrolling painful. */
interface Option { value: string, label: string }

const props = defineProps<{
  modelValue: string
  options: Option[]
  title: string
  placeholder?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [string] }>()

const open = ref(false)
const q = ref('')

const selected = computed(() => props.options.find(o => o.value === props.modelValue) ?? null)

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return props.options
  return props.options.filter(o => o.label.toLowerCase().includes(term))
})

function choose(o: Option) {
  emit('update:modelValue', o.value)
  open.value = false
  q.value = ''
}

function toggle() {
  open.value = !open.value
  if (open.value) q.value = ''
}

function closeOnOutside(e: MouseEvent) {
  const t = e.target as HTMLElement
  if (!t?.closest?.('.ss')) open.value = false
}

onMounted(() => document.addEventListener('click', closeOnOutside))
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutside))
</script>

<template>
  <div class="ss">
    <button type="button" class="box" :class="{ open }" @click="toggle">
      <span :class="{ ph: !selected }">{{ selected ? selected.label : (placeholder || 'Select') }}</span>
      <svg class="chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
    </button>

    <div v-if="open" class="panel">
      <p class="title">{{ title }}</p>
      <div class="search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
        <input v-model="q" type="text" placeholder="Search..." autofocus>
      </div>
      <div class="opts">
        <button
          v-for="o in filtered" :key="o.value" type="button"
          class="opt" :class="{ on: o.value === modelValue }" @click="choose(o)"
        >
          {{ o.label }}
        </button>
        <p v-if="!filtered.length" class="empty">No results</p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ss { position: relative; }

.box {
  display: flex; align-items: center; justify-content: space-between; width: 100%;
  border: 1px solid #d7dae1; border-radius: 10px; padding: 11px 13px; font: inherit; font-size: .9rem;
  color: #1e293b; background: #fff; cursor: pointer; text-align: left;
}
.box .ph { color: #94a3b8; }
.box.open { border-color: var(--brand-primary); }
.box .chev { flex: 0 0 auto; width: 16px; height: 16px; fill: none; stroke: #94a3b8; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.panel {
  position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; right: 0;
  background: #fff; border: 1px solid #e5e8ee; border-radius: 12px; box-shadow: 0 12px 28px rgba(15,23,42,.12);
  padding: 12px; display: flex; flex-direction: column; gap: 10px;
}
.title { margin: 0; font-size: .78rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .03em; }

.search { display: flex; align-items: center; gap: 8px; border: 1px solid #e5e8ee; border-radius: 9px; padding: 8px 11px; }
.search svg { flex: 0 0 auto; width: 15px; height: 15px; fill: none; stroke: #94a3b8; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.search input { flex: 1; border: none; outline: none; font: inherit; font-size: .87rem; color: #1e293b; }

.opts { max-height: 220px; overflow-y: auto; display: flex; flex-direction: column; gap: 1px; }
.opt {
  border: none; background: none; text-align: left; padding: 9px 10px; border-radius: 8px;
  font: inherit; font-size: .88rem; color: #334155; cursor: pointer;
}
.opt:hover { background: #f7f8fa; }
.opt.on { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 600; }
.empty { margin: 6px 0; font-size: .85rem; color: #94a3b8; text-align: center; }
</style>

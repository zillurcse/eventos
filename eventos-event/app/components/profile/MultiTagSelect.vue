<script setup lang="ts">
/**
 * Multi-select tag picker for Profile › Looking & Offering. The option list is
 * fed from the event feed's looking_for / offering tags (see FeedController
 * @networkingTags) — the user picks any number of them. A sparse feed shouldn't
 * trap anyone, so a term typed in the search that isn't listed can be added as a
 * new tag. Popover mechanics mirror SearchSelect, but the value is an array.
 */
const props = defineProps<{
  modelValue: string[]
  options: string[]
  placeholder?: string
  max?: number
}>()

const emit = defineEmits<{ 'update:modelValue': [string[]] }>()

const open = ref(false)
const q = ref('')

const max = computed(() => props.max ?? 12)
const atMax = computed(() => props.modelValue.length >= max.value)

const filtered = computed(() => {
  const term = q.value.trim().toLowerCase()
  return props.options.filter(o => !term || o.toLowerCase().includes(term))
})

// The typed term is a brand-new tag when the feed has never seen it and it isn't
// already chosen — offered as "Add" below the list.
const canAdd = computed(() => {
  const term = q.value.trim().toLowerCase()
  if (!term) return false
  return !props.options.some(o => o.toLowerCase() === term)
    && !props.modelValue.some(v => v.toLowerCase() === term)
})

function isOn(o: string) { return props.modelValue.some(v => v.toLowerCase() === o.toLowerCase()) }

function toggle(o: string) {
  if (isOn(o)) emit('update:modelValue', props.modelValue.filter(v => v.toLowerCase() !== o.toLowerCase()))
  else if (!atMax.value) emit('update:modelValue', [...props.modelValue, o])
}

function addTyped() {
  const term = q.value.trim()
  if (!term || atMax.value || !canAdd.value) return
  emit('update:modelValue', [...props.modelValue, term])
  q.value = ''
}

function remove(v: string) { emit('update:modelValue', props.modelValue.filter(x => x !== v)) }

function toggleOpen() {
  open.value = !open.value
  if (open.value) q.value = ''
}

function closeOnOutside(e: MouseEvent) {
  const t = e.target as HTMLElement
  if (!t?.closest?.('.ms')) open.value = false
}

onMounted(() => document.addEventListener('click', closeOnOutside))
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutside))
</script>

<template>
  <div class="ms">
    <div v-if="modelValue.length" class="chips">
      <span v-for="v in modelValue" :key="v" class="chip">
        {{ v }}<button type="button" title="Remove" @click="remove(v)">×</button>
      </span>
    </div>

    <button type="button" class="box" :class="{ open }" @click="toggleOpen">
      <span class="ph">{{ placeholder || 'Select Options' }}</span>
      <svg class="chev" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
    </button>

    <div v-if="open" class="panel">
      <div class="search">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></svg>
        <input v-model="q" type="text" placeholder="Search or add…" autofocus @keydown.enter.prevent="addTyped">
      </div>
      <div class="opts">
        <button
          v-for="o in filtered" :key="o" type="button" class="opt" :class="{ on: isOn(o) }"
          :disabled="!isOn(o) && atMax" @click="toggle(o)"
        >
          <span class="tick"><svg v-if="isOn(o)" viewBox="0 0 24 24"><path d="m5 12 4.2 4.2L19 6.5" /></svg></span>
          {{ o }}
        </button>
        <button v-if="canAdd && !atMax" type="button" class="opt add" @click="addTyped">
          <svg class="plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14" /></svg>
          Add “{{ q.trim() }}”
        </button>
        <p v-if="!filtered.length && !canAdd" class="empty">
          {{ atMax ? 'You’ve reached the limit.' : 'No options yet — type to add one.' }}
        </p>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ms { position: relative; }

.chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 10px; }
.chip { display: inline-flex; align-items: center; gap: 6px; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); border-radius: 999px; padding: 6px 12px; font-size: .84rem; font-weight: 600; }
.chip button { border: none; background: none; color: inherit; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }

.box {
  display: flex; align-items: center; justify-content: space-between; width: 100%;
  border: 1px solid #d7dae1; border-radius: 10px; padding: 12px 14px; font: inherit; font-size: .9rem;
  color: #1e293b; background: #fff; cursor: pointer; text-align: left;
}
.box .ph { color: #94a3b8; }
.box.open { border-color: var(--brand-primary); }
.box .chev { flex: 0 0 auto; width: 16px; height: 16px; fill: none; stroke: #94a3b8; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; transition: transform .15s; }
.box.open .chev { transform: rotate(180deg); }

.panel {
  position: absolute; z-index: 20; top: calc(100% + 6px); left: 0; right: 0;
  background: #fff; border: 1px solid #e5e8ee; border-radius: 12px; box-shadow: 0 12px 28px rgba(15,23,42,.12);
  padding: 12px; display: flex; flex-direction: column; gap: 10px;
}

.search { display: flex; align-items: center; gap: 8px; border: 1px solid #e5e8ee; border-radius: 9px; padding: 8px 11px; }
.search svg { flex: 0 0 auto; width: 15px; height: 15px; fill: none; stroke: #94a3b8; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
.search input { flex: 1; border: none; outline: none; font: inherit; font-size: .87rem; color: #1e293b; }

.opts { max-height: 240px; overflow-y: auto; display: flex; flex-direction: column; gap: 1px; }
.opt {
  display: flex; align-items: center; gap: 9px; border: none; background: none; text-align: left;
  padding: 9px 10px; border-radius: 8px; font: inherit; font-size: .88rem; color: #334155; cursor: pointer;
}
.opt:not(:disabled):hover { background: #f7f8fa; }
.opt.on { background: color-mix(in srgb, var(--brand-primary) 10%, #fff); color: var(--brand-primary); font-weight: 600; }
.opt:disabled { opacity: .45; cursor: default; }

.tick { flex: 0 0 auto; width: 16px; height: 16px; border: 1.5px solid #cbd5e1; border-radius: 5px; display: grid; place-items: center; }
.opt.on .tick { border-color: var(--brand-primary); background: var(--brand-primary); }
.tick svg { width: 11px; height: 11px; fill: none; stroke: #fff; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }

.opt.add { color: var(--brand-primary); font-weight: 600; }
.plus { flex: 0 0 auto; width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; }

.empty { margin: 6px 0; font-size: .85rem; color: #94a3b8; text-align: center; }
</style>

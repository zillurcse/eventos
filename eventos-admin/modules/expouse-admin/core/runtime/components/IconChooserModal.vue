<script setup lang="ts">
interface IconOption {
  key: string
  label: string
  category: string | null
}

const props = withDefaults(defineProps<{
  modelValue?: string | null
  title?: string
}>(), { title: 'Choose Icon' })

const emit = defineEmits<{
  (e: 'select', name: string): void
  (e: 'close'): void
}>()

const api = useApi()

const search = ref('')
const picked = ref(props.modelValue || '')
const icons = ref<IconOption[]>([])
const loading = ref(true)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    icons.value = (await api<{ data: IconOption[] }>('/icons')).data
  } catch {
    error.value = 'Could not load icons.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return icons.value
  return icons.value.filter(i => i.label.toLowerCase().includes(q) || i.key.toLowerCase().includes(q))
})

function confirm() {
  if (!picked.value) return
  emit('select', picked.value)
  emit('close')
}
</script>

<template>
  <Modal :title="title" @close="emit('close')">
    <AppInput v-model="search" placeholder="Search icons" class="mb-4" />

    <p v-if="loading" class="text-muted text-[.88rem] py-8 text-center">Loading icons…</p>
    <p v-else-if="error" class="text-[#dc2626] text-[.88rem] py-8 text-center">{{ error }}</p>
    <div v-else-if="filtered.length" class="grid grid-cols-6 gap-2.5 max-h-[50vh] overflow-auto pr-0.5">
      <button
        v-for="icon in filtered" :key="icon.key"
        type="button"
        class="aspect-square rounded-[11px] border flex items-center justify-center bg-white cursor-pointer"
        :class="picked === icon.key ? 'border-brand border-2 text-brand' : 'border-line text-muted hover:border-[#c7cad3]'"
        :title="icon.label"
        @click="picked = icon.key"
      >
        <AppIcon :name="icon.key" class="w-5.5 h-5.5" />
      </button>
    </div>
    <p v-else class="text-muted text-[.88rem] py-8 text-center">No icons match your search.</p>

    <div class="modal-actions">
      <button class="btn ghost" @click="emit('close')">Cancel</button>
      <button class="btn" :disabled="!picked" @click="confirm">Select</button>
    </div>
  </Modal>
</template>

<script setup lang="ts">
/**
 * Snapshot history for a saved template. Restoring is non-destructive — the API
 * snapshots the current state before rolling back, so it can be undone by
 * restoring the version that was just created.
 */
interface Version {
  version: number
  name: string | null
  subject: string | null
  blocks: number
  author: string | null
  created_at: string | null
}

const props = defineProps<{ templateId: string }>()
const emit = defineEmits<{ (e: 'restored'): void, (e: 'close'): void }>()

const api = useApi()

const versions = ref<Version[]>([])
const loading = ref(true)
const restoring = ref<number | null>(null)
const error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    versions.value = (await api<{ data: Version[] }>(`/email-templates/${props.templateId}/versions`)).data
  } catch {
    error.value = 'Could not load version history.'
  } finally {
    loading.value = false
  }
}

async function restore(v: Version) {
  if (restoring.value !== null) return
  if (!confirm(`Restore version ${v.version}? Your current draft is saved to history first, so this can be undone.`)) return

  restoring.value = v.version
  error.value = ''
  try {
    await api(`/email-templates/${props.templateId}/versions/${v.version}/restore`, { method: 'POST' })
    emit('restored')
  } catch (e: any) {
    error.value = e?.data?.message || 'Restore failed.'
  } finally {
    restoring.value = null
  }
}

function fmtDate(d: string | null) {
  if (!d) return '—'
  return new Date(d).toLocaleString(undefined, {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div class="fixed inset-0 z-[170] bg-black/40 flex justify-end" @click.self="emit('close')">
    <aside class="w-[420px] max-w-full bg-white h-full flex flex-col shadow-xl">
      <header class="flex items-center gap-3 p-4 border-b border-line">
        <div class="flex-1">
          <h3 class="m-0 text-[1.05rem]">Version history</h3>
          <p class="muted text-[.8rem] m-0">Every save is snapshotted. Restoring never loses your current draft.</p>
        </div>
        <button class="w-8 h-8 rounded-lg border border-line grid place-items-center cursor-pointer hover:bg-[#f5f5fa]" title="Close" @click="emit('close')">✕</button>
      </header>

      <p v-if="error" class="text-[#dc2626] text-[.82rem] px-4 pt-3 mb-0">{{ error }}</p>

      <div class="flex-1 overflow-y-auto p-4">
        <p v-if="loading" class="muted text-center py-10 m-0">Loading history…</p>

        <p v-else-if="!versions.length" class="muted text-center py-10 m-0 text-[.88rem]">
          No history yet — it starts building from your next save.
        </p>

        <ol v-else class="list-none m-0 p-0 flex flex-col gap-2">
          <li
            v-for="(v, i) in versions"
            :key="v.version"
            class="border border-line rounded-xl p-3"
            :class="i === 0 ? 'bg-[#f5f3ff] border-[#c7c2f5]' : 'bg-white'"
          >
            <div class="flex items-center gap-2">
              <span class="font-semibold text-[.88rem]">Version {{ v.version }}</span>
              <span v-if="i === 0" class="text-[.62rem] uppercase tracking-wide px-1.5 py-0.5 rounded bg-[#6352e7] text-white">Latest</span>
              <button
                v-if="i !== 0"
                class="ml-auto text-[.78rem] text-[#6352e7] font-semibold bg-transparent border-0 cursor-pointer disabled:opacity-40"
                :disabled="restoring !== null"
                @click="restore(v)"
              >{{ restoring === v.version ? 'Restoring…' : 'Restore' }}</button>
            </div>
            <p class="m-0 mt-1 text-[.8rem] truncate">{{ v.subject || v.name || 'No subject' }}</p>
            <p class="m-0 mt-0.5 muted text-[.72rem]">
              {{ fmtDate(v.created_at) }} · {{ v.blocks }} block{{ v.blocks === 1 ? '' : 's' }}
              <template v-if="v.author"> · {{ v.author }}</template>
            </p>
          </li>
        </ol>
      </div>
    </aside>
  </div>
</template>

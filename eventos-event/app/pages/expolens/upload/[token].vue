<script setup lang="ts">
/**
 * Standalone photographer drop page (ExpoLens › Photographer Links).
 *
 * No auth and no subdomain requirement — the token in the URL is the whole
 * capability, exactly like the public form page. Files are posted one at a
 * time so a dropped shoot reports per-file progress and a single rejected
 * frame never sinks the batch.
 */
definePageMeta({ layout: false })

const route = useRoute()
const api = useApi()
const token = route.params.token as string

interface LinkInfo {
  label: string
  album: string | null
  event_name: string | null
  remaining: number | null
  expires_at: string | null
  auto_approve: boolean
}

interface Row {
  name: string
  status: 'uploading' | 'done' | 'error'
  message?: string
}

const info = ref<LinkInfo | null>(null)
const loading = ref(true)
const loadError = ref('')
const fileInput = ref<HTMLInputElement | null>(null)
const rows = ref<Row[]>([])
const busy = ref(false)
const dragging = ref(false)

const uploaded = computed(() => rows.value.filter(r => r.status === 'done').length)
const failed = computed(() => rows.value.filter(r => r.status === 'error').length)

onMounted(async () => {
  try {
    const res = await api<{ data: LinkInfo }>(`/public/expolens/upload/${token}`)
    info.value = res.data
  } catch (e: any) {
    loadError.value = e?.data?.message || 'This upload link is not valid.'
  } finally {
    loading.value = false
  }
})

function pick() {
  fileInput.value?.click()
}

function onDrop(e: DragEvent) {
  dragging.value = false
  const files = Array.from(e.dataTransfer?.files ?? [])
  if (files.length) void send(files)
}

function onPicked(e: Event) {
  const input = e.target as HTMLInputElement
  const files = Array.from(input.files ?? [])
  input.value = ''
  if (files.length) void send(files)
}

async function send(files: File[]) {
  const images = files.filter(f => /^image\/(jpeg|png|webp|jpg)$/.test(f.type))
  const skipped = files.length - images.length
  if (skipped > 0) {
    rows.value.push({ name: `${skipped} non-image file(s)`, status: 'error', message: 'Only JPEG, PNG or WebP' })
  }
  if (!images.length) return

  busy.value = true
  for (const file of images) {
    const row = reactive<Row>({ name: file.name, status: 'uploading' })
    rows.value.unshift(row)

    try {
      const body = new FormData()
      body.append('file', file)
      await api(`/public/expolens/upload/${token}`, { method: 'POST', body })
      row.status = 'done'
      if (info.value?.remaining != null) info.value.remaining = Math.max(0, info.value.remaining - 1)
    } catch (e: any) {
      row.status = 'error'
      row.message = e?.data?.message || 'Upload failed'
    }
  }
  busy.value = false
}
</script>

<template>
  <div class="wrap">
    <div v-if="loading" class="state">Checking your upload link…</div>

    <div v-else-if="loadError" class="state error">
      <h1>Link unavailable</h1>
      <p>{{ loadError }}</p>
      <p class="hint">Ask the event organizer for a fresh upload link.</p>
    </div>

    <template v-else-if="info">
      <header>
        <p class="eyebrow">{{ info.event_name || 'Event photos' }}</p>
        <h1>Upload photos</h1>
        <p class="sub">
          You're uploading as <strong>{{ info.label }}</strong>
          <span v-if="info.album"> into the <strong>{{ info.album }}</strong> album</span>.
          Faces are matched automatically so attendees can find their own shots.
        </p>
        <p v-if="info.remaining != null" class="sub">
          {{ info.remaining }} upload{{ info.remaining === 1 ? '' : 's' }} remaining on this link.
        </p>
      </header>

      <div
        class="drop"
        :class="{ over: dragging, busy }"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="onDrop"
        @click="pick"
      >
        <input
          ref="fileInput"
          type="file"
          class="hidden"
          accept="image/jpeg,image/png,image/webp,image/jpg"
          multiple
          @change="onPicked"
        >
        <strong>{{ busy ? 'Uploading…' : 'Drop photos here' }}</strong>
        <span>or click to choose — JPEG, PNG or WebP, up to 25 MB each</span>
      </div>

      <p v-if="rows.length" class="tally">
        {{ uploaded }} uploaded<span v-if="failed"> · {{ failed }} failed</span>
      </p>

      <ul v-if="rows.length" class="rows">
        <li v-for="(row, i) in rows" :key="`${row.name}-${i}`" :class="row.status">
          <span class="name">{{ row.name }}</span>
          <span class="status">
            {{ row.status === 'uploading' ? 'Uploading…' : row.status === 'done' ? 'Uploaded' : row.message }}
          </span>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.wrap { max-width: 720px; margin: 0 auto; padding: 40px 20px 64px; font-family: system-ui, sans-serif; }
.state { text-align: center; color: #64748b; padding: 60px 12px; }
.state.error h1 { color: #0f172a; font-size: 1.4rem; margin: 0 0 8px; }
.hint { font-size: .88rem; }
header { margin-bottom: 24px; }
.eyebrow { margin: 0; text-transform: uppercase; letter-spacing: .08em; font-size: .75rem; color: #6352e7; font-weight: 700; }
h1 { margin: 6px 0 8px; font-size: 1.7rem; color: #0f172a; }
.sub { margin: 0 0 4px; color: #64748b; line-height: 1.5; }
.drop {
  border: 2px dashed #cbd5e1; border-radius: 16px; background: #f8fafc;
  padding: 44px 20px; text-align: center; cursor: pointer; transition: all .15s;
  display: flex; flex-direction: column; gap: 6px;
}
.drop:hover, .drop.over { border-color: #6352e7; background: #f3f0ff; }
.drop.busy { opacity: .7; pointer-events: none; }
.drop strong { color: #0f172a; font-size: 1.05rem; }
.drop span { color: #64748b; font-size: .88rem; }
.hidden { display: none; }
.tally { margin: 18px 0 8px; font-weight: 600; color: #0f172a; }
.rows { list-style: none; padding: 0; margin: 0; display: grid; gap: 6px; }
.rows li {
  display: flex; justify-content: space-between; gap: 12px; align-items: center;
  border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 12px; background: #fff; font-size: .88rem;
}
.rows li.done { border-color: #a7f3d0; background: #f0fdf9; }
.rows li.error { border-color: #fecaca; background: #fef2f2; }
.name { color: #0f172a; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.status { color: #64748b; white-space: nowrap; }
.rows li.done .status { color: #047857; }
.rows li.error .status { color: #b91c1c; }
</style>

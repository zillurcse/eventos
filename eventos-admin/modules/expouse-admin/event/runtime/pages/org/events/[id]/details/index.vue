<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const form = reactive<{
  name: string, starts_at: string, ends_at: string, description: string,
  timezone: string, format: string, address: string, url: string,
}>({ name: '', starts_at: '', ends_at: '', description: '', timezone: 'UTC', format: 'venue', address: '', url: '' })

const saved = ref(false)
const error = ref('')

const DELIVERY = [
  { value: 'venue', label: 'In person', icon: 'M12 13.43a3.12 3.12 0 100-6.24 3.12 3.12 0 000 6.24z M3.62 8.49c1.97-8.66 14.8-8.65 16.76.01 1.15 5.08-2.01 9.38-4.78 12.04a5.193 5.193 0 01-7.21 0c-2.76-2.66-5.92-6.97-4.77-12.05z' },
  { value: 'online', label: 'Online', icon: 'M2 12.5h20M2 7.5h20M2 17.5h13' },
  { value: 'hybrid', label: 'Hybrid', icon: 'M3.17 7.44L12 12.55l8.77-5.08M12 22.08V12.54' },
]
const showAddress = computed(() => form.format === 'venue' || form.format === 'hybrid')
const showUrl = computed(() => form.format === 'online' || form.format === 'hybrid')

const zones = computed<string[]>(() => {
  let list: string[] = []
  try { list = (Intl as any).supportedValuesOf?.('timeZone') ?? [] } catch { /* */ }
  if (!list.length) list = ['UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Asia/Dubai', 'Asia/Kolkata', 'Asia/Dhaka', 'Asia/Singapore', 'Asia/Tokyo', 'Australia/Sydney']
  if (form.timezone && !list.includes(form.timezone)) list = [form.timezone, ...list]
  return list
})

function toLocal(iso: string | null) { return iso ? new Date(iso).toISOString().slice(0, 16) : '' }

async function load() {
  const e = (await api<any>(`/events/${id}`)).data
  form.name = e.name
  form.starts_at = toLocal(e.starts_at)
  form.ends_at = toLocal(e.ends_at)
  form.description = e.description ?? ''
  form.timezone = e.timezone ?? 'UTC'
  form.format = e.format ?? 'venue'
  form.address = e.location?.address ?? ''
  form.url = e.location?.url ?? ''
}

const saving = ref(false)

async function save() {
  error.value = ''
  saving.value = true
  try {
    await api(`/events/${id}`, { method: 'PATCH', body: {
      name: form.name,
      // Send empty string as null so dates can be cleared; only omit when unchanged-empty.
      starts_at: form.starts_at || null,
      ends_at: form.ends_at || null,
      description: form.description || null,
      timezone: form.timezone || 'UTC',
      format: form.format,
      location: {
        address: showAddress.value ? (form.address || null) : null,
        url: showUrl.value ? (form.url || null) : null,
      },
    } })
    // Re-sync from the DB so the form visibly reflects what was persisted.
    await load()
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
    toast.success('Event details saved')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
    toast.error(error.value)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="card max-w-[720px]">
    <h2>General information <span v-if="saved" class="badge active">saved ✓</span></h2>

    <label>Event Title</label>
    <input v-model="form.name" placeholder="e.g. AI Expo 2026">

    <div class="flex gap-3">
      <div class="flex-1"><label>Event Start</label><input v-model="form.starts_at" type="datetime-local"></div>
      <div class="flex-1"><label>Event End</label><input v-model="form.ends_at" type="datetime-local"></div>
    </div>

    <label>Event Description</label>
    <textarea v-model="form.description" rows="3" placeholder="What's this event about?" />

    <label>Time Zone</label>
    <select v-model="form.timezone">
      <option v-for="z in zones" :key="z" :value="z">{{ z }}</option>
    </select>

    <label class="mt-3.5 block">Event Delivery</label>
    <div class="flex gap-2.5">
      <button
        v-for="d in DELIVERY" :key="d.value" type="button"
        class="flex-1 flex flex-col items-center gap-1.5 p-3.5 px-2 border border-[1.5px] border-line rounded-xl bg-white cursor-pointer font-semibold text-[.9rem] text-muted transition-all duration-150 hover:border-[#c7c2f5] hover:text-[#6352e7]"
        :class="{ 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]': form.format === d.value }"
        @click="form.format = d.value"
      >
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path v-for="(p, i) in d.icon.split(' M').map((s, i) => (i ? 'M' + s : s))" :key="i" :d="p" /></svg>
        {{ d.label }}
      </button>
    </div>

    <label class="mt-3.5 block">Location</label>
    <div v-if="showAddress">
      <input v-model="form.address" placeholder="Venue name & address">
    </div>
    <div v-if="showUrl">
      <input v-model="form.url" placeholder="https://… (meeting / stream link)">
    </div>
    <p class="muted text-[.82rem] mt-0.5">
      <template v-if="form.format === 'venue'">A physical venue for in-person attendees.</template>
      <template v-else-if="form.format === 'online'">A streaming / meeting link for online attendees.</template>
      <template v-else>A physical venue and an online link — hybrid events have both.</template>
    </p>

    <p v-if="error" class="error">{{ error }}</p>
    <div class="mt-4"><button class="btn" :disabled="!form.name || saving" @click="save">{{ saving ? 'Saving…' : 'Save changes' }}</button></div>
  </div>
</template>

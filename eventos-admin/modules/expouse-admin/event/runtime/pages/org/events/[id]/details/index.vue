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
  {
    value: 'venue', label: 'In Person',
    icon: 'M12 13.43a3.12 3.12 0 100-6.24 3.12 3.12 0 000 6.24z M3.62 8.49c1.97-8.66 14.8-8.65 16.76.01 1.15 5.08-2.01 9.38-4.78 12.04a5.193 5.193 0 01-7.21 0c-2.76-2.66-5.92-6.97-4.77-12.05z',
    desc: 'Physical venue for in-person attendees',
  },
  {
    value: 'online', label: 'Online',
    icon: 'M2 12.5h20M2 7.5h20M2 17.5h13',
    desc: 'Streaming or meeting link for remote attendees',
  },
  {
    value: 'hybrid', label: 'Hybrid',
    icon: 'M3.17 7.44L12 12.55l8.77-5.08M12 22.08V12.54',
    desc: 'Both physical venue and an online link',
  },
]

const showAddress = computed(() => form.format === 'venue' || form.format === 'hybrid')
const showUrl     = computed(() => form.format === 'online' || form.format === 'hybrid')

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
  form.name        = e.name
  form.starts_at   = toLocal(e.starts_at)
  form.ends_at     = toLocal(e.ends_at)
  form.description = e.description ?? ''
  form.timezone    = e.timezone ?? 'UTC'
  form.format      = e.format ?? 'venue'
  form.address     = e.location?.address ?? ''
  form.url         = e.location?.url ?? ''
}

const saving = ref(false)

async function save() {
  error.value = ''
  saving.value = true
  try {
    await api(`/events/${id}`, { method: 'PATCH', body: {
      name:        form.name,
      starts_at:   form.starts_at || null,
      ends_at:     form.ends_at || null,
      description: form.description || null,
      timezone:    form.timezone || 'UTC',
      format:      form.format,
      location: {
        address: showAddress.value ? (form.address || null) : null,
        url:     showUrl.value     ? (form.url || null)     : null,
      },
    } })
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
  <div class="max-w-180">

    <!-- Page header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">General Information</h1>
        <p class="text-muted text-[.88rem]">Update your event's core details and delivery format.</p>
      </div>
      <button
        class="btn"
        :disabled="!form.name || saving"
        @click="save"
      >
        <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
        </svg>
        <svg v-else-if="saved" width="15" height="15" viewBox="0 0 24 24" fill="none">
          <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save changes' }}
      </button>
    </div>

    <!-- ── Basic information ──────────────────────────────── -->
    <div class="card mb-4">
      <div class="flex items-center gap-2.5 mb-5">
        <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <path d="M21 7v10c0 3-1.5 5-5 5H8c-3.5 0-5-2-5-5V7c0-3 1.5-5 5-5h8c3.5 0 5 2 5 5z"/><path d="M14.5 4.5v2c0 1.1.9 2 2 2h2M8 13h4M8 17h8"/>
          </svg>
        </div>
        <h2 class="mb-0!">Event Details</h2>
      </div>

      <div class="flex flex-col gap-4">
        <AppInput
          v-model="form.name"
          label="Event Title"
          placeholder="e.g. AI Expo 2026"
          required
        />

        <div class="grid grid-cols-2 gap-3">
          <AppInput
            v-model="form.starts_at"
            label="Start Date & Time"
            type="datetime-local"
          />
          <AppInput
            v-model="form.ends_at"
            label="End Date & Time"
            type="datetime-local"
          />
        </div>

        <AppTextarea
          v-model="form.description"
          label="Event Description"
          placeholder="What's this event about?"
          :rows="4"
          resize="vertical"
          hint="Give attendees a clear overview of what to expect."
        />

        <AppSelect
          v-model="form.timezone"
          label="Time Zone"
        >
          <option v-for="z in zones" :key="z" :value="z">{{ z }}</option>
        </AppSelect>
      </div>
    </div>

    <!-- ── Format & location ──────────────────────────────── -->
    <div class="card mb-4">
      <div class="flex items-center gap-2.5 mb-5">
        <div class="w-7 h-7 rounded-lg bg-brand-soft grid place-items-center shrink-0">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
            <path d="M12 13.43a3.12 3.12 0 100-6.24 3.12 3.12 0 000 6.24z"/><path d="M3.62 8.49c1.97-8.66 14.8-8.65 16.76.01 1.15 5.08-2.01 9.38-4.78 12.04a5.193 5.193 0 01-7.21 0c-2.76-2.66-5.92-6.97-4.77-12.05z"/>
          </svg>
        </div>
        <h2 class="mb-0!">Format & Location</h2>
      </div>

      <!-- Delivery type selector -->
      <FormField label="Event Delivery" class="mb-4">
        <div class="grid grid-cols-3 gap-2.5 mt-1">
          <button
            v-for="d in DELIVERY" :key="d.value" type="button"
            class="flex flex-col items-center gap-2 px-3 py-4 rounded-xl border-[1.5px] cursor-pointer transition-all duration-150"
            :class="form.format === d.value
              ? 'border-brand bg-brand-soft text-brand'
              : 'border-line bg-white text-muted hover:border-[#c7c2f5] hover:text-brand'"
            @click="form.format = d.value"
          >
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path v-for="(p, i) in d.icon.split(' M').map((s, i) => (i ? 'M' + s : s))" :key="i" :d="p" />
            </svg>
            <span class="font-semibold text-[.88rem] leading-none">{{ d.label }}</span>
            <span class="text-[.75rem] text-center leading-snug opacity-70">{{ d.desc }}</span>
          </button>
        </div>
      </FormField>

      <!-- Location fields -->
      <div class="flex flex-col gap-3">
        <AppInput
          v-if="showAddress"
          v-model="form.address"
          label="Venue Address"
          placeholder="e.g. Convention Center, 123 Main St, New York"
        >
          <template #prefix>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint">
              <path d="M12 13.43a3.12 3.12 0 100-6.24 3.12 3.12 0 000 6.24z"/><path d="M3.62 8.49c1.97-8.66 14.8-8.65 16.76.01 1.15 5.08-2.01 9.38-4.78 12.04a5.193 5.193 0 01-7.21 0c-2.76-2.66-5.92-6.97-4.77-12.05z"/>
            </svg>
          </template>
        </AppInput>
        <AppInput
          v-if="showUrl"
          v-model="form.url"
          label="Online Link"
          placeholder="https://… (meeting or stream URL)"
        >
          <template #prefix>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-faint">
              <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
            </svg>
          </template>
        </AppInput>
      </div>
    </div>

    <!-- Error -->
    <p v-if="error" class="error mb-4">{{ error }}</p>

  </div>
</template>

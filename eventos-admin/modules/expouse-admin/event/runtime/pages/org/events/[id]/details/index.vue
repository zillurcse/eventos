<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const form = reactive<{
  name: string, sector: string, starts_at: string, ends_at: string, description: string,
  timezone: string, format: string,
  venue: string, street: string, address_line_1: string, address_line_2: string,
  country: string, state: string, city: string, zip: string, url: string,
}>({
  name: '', sector: '', starts_at: '', ends_at: '', description: '',
  timezone: 'UTC', format: 'venue',
  venue: '', street: '', address_line_1: '', address_line_2: '',
  country: '', state: '', city: '', zip: '', url: '',
})

const saved = ref(false)
const error = ref('')

const SECTORS = [
  'Technology', 'Healthcare & Medical', 'Finance & Banking', 'Education',
  'Government & Public Sector', 'Manufacturing & Industrial', 'Retail & E-commerce',
  'Real Estate & Construction', 'Automotive', 'Energy & Utilities',
  'Travel & Hospitality', 'Media & Entertainment', 'Non-Profit & NGO',
  'Sports & Fitness', 'Food & Beverage', 'Fashion & Apparel', 'Other',
]

const COUNTRIES = [
  'Afghanistan', 'Albania', 'Algeria', 'Andorra', 'Angola', 'Argentina', 'Armenia',
  'Australia', 'Austria', 'Azerbaijan', 'Bahamas', 'Bahrain', 'Bangladesh', 'Barbados',
  'Belarus', 'Belgium', 'Belize', 'Benin', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina',
  'Botswana', 'Brazil', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia',
  'Cameroon', 'Canada', 'Chad', 'Chile', 'China', 'Colombia', 'Congo', 'Costa Rica',
  'Croatia', 'Cuba', 'Cyprus', 'Czechia', 'Denmark', 'Djibouti', 'Dominican Republic',
  'Ecuador', 'Egypt', 'El Salvador', 'Estonia', 'Eswatini', 'Ethiopia', 'Fiji', 'Finland',
  'France', 'Gabon', 'Gambia', 'Georgia', 'Germany', 'Ghana', 'Greece', 'Guatemala',
  'Guinea', 'Guyana', 'Haiti', 'Honduras', 'Hong Kong', 'Hungary', 'Iceland', 'India',
  'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Italy', 'Jamaica', 'Japan', 'Jordan',
  'Kazakhstan', 'Kenya', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho',
  'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Madagascar', 'Malawi',
  'Malaysia', 'Maldives', 'Mali', 'Malta', 'Mauritania', 'Mauritius', 'Mexico', 'Moldova',
  'Monaco', 'Mongolia', 'Montenegro', 'Morocco', 'Mozambique', 'Myanmar', 'Namibia',
  'Nepal', 'Netherlands', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'North Macedonia',
  'Norway', 'Oman', 'Pakistan', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru',
  'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'Russia', 'Rwanda',
  'Saudi Arabia', 'Senegal', 'Serbia', 'Singapore', 'Slovakia', 'Slovenia', 'Somalia',
  'South Africa', 'South Korea', 'South Sudan', 'Spain', 'Sri Lanka', 'Sudan', 'Sweden',
  'Switzerland', 'Syria', 'Taiwan', 'Tajikistan', 'Tanzania', 'Thailand', 'Togo',
  'Trinidad and Tobago', 'Tunisia', 'Turkey', 'Turkmenistan', 'Uganda', 'Ukraine',
  'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan',
  'Venezuela', 'Vietnam', 'Yemen', 'Zambia', 'Zimbabwe',
]

const DELIVERY = [
  {
    value: 'venue', label: 'In-person',
    icon: 'M12 13.43a3.12 3.12 0 100-6.24 3.12 3.12 0 000 6.24z M3.62 8.49c1.97-8.66 14.8-8.65 16.76.01 1.15 5.08-2.01 9.38-4.78 12.04a5.193 5.193 0 01-7.21 0c-2.76-2.66-5.92-6.97-4.77-12.05z',
  },
  {
    value: 'online', label: 'Virtual',
    icon: 'M15 8.5l5.19-2.6a1 1 0 011.31 1v10.2a1 1 0 01-1.31 1L15 15.5 M3 7.5h10a2 2 0 012 2v5a2 2 0 01-2 2H3a2 2 0 01-2-2v-5a2 2 0 012-2z',
  },
  {
    value: 'hybrid', label: 'Hybrid',
    icon: 'M3 5.5h18v11H3z M8 20h8 M12 16.5V20',
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
  form.name           = e.name
  form.sector         = e.sector ?? ''
  form.starts_at      = toLocal(e.starts_at)
  form.ends_at        = toLocal(e.ends_at)
  form.description     = e.description ?? ''
  form.timezone       = e.timezone ?? 'UTC'
  form.format         = e.format ?? 'venue'
  form.venue          = e.location?.venue ?? ''
  form.street         = e.location?.street ?? ''
  form.address_line_1 = e.location?.address_line_1 ?? ''
  form.address_line_2 = e.location?.address_line_2 ?? ''
  form.country        = e.location?.country ?? ''
  form.state          = e.location?.state ?? ''
  form.city           = e.location?.city ?? ''
  form.zip            = e.location?.zip ?? ''
  form.url            = e.location?.url ?? ''
}

const saving = ref(false)

async function save() {
  error.value = ''
  saving.value = true
  try {
    await api(`/events/${id}`, { method: 'PATCH', body: {
      name:        form.name,
      sector:      form.sector || null,
      starts_at:   form.starts_at || null,
      ends_at:     form.ends_at || null,
      description: form.description || null,
      timezone:    form.timezone || 'UTC',
      format:      form.format,
      location: {
        venue:          showAddress.value ? (form.venue || null) : null,
        street:         showAddress.value ? (form.street || null) : null,
        address_line_1: showAddress.value ? (form.address_line_1 || null) : null,
        address_line_2: showAddress.value ? (form.address_line_2 || null) : null,
        country:        showAddress.value ? (form.country || null) : null,
        state:          showAddress.value ? (form.state || null) : null,
        city:           showAddress.value ? (form.city || null) : null,
        zip:            showAddress.value ? (form.zip || null) : null,
        url:            showUrl.value     ? (form.url || null)  : null,
      },
    } })
    saved.value = true; setTimeout(() => (saved.value = false), 1500)
    toast.success('Event details saved')
  } catch (e: any) {
    error.value = e?.data?.message || 'Could not save.'
    toast.error(error.value)
  } finally {
    saving.value = false
  }
}

function cancel() {
  error.value = ''
  load()
}

onMounted(load)
</script>

<template>
  <div class="max-w-full">

    <h1 class="text-[1.35rem] font-bold text-ink mb-5">Basic Information</h1>

    <div class="card">
      <div class="mb-5">
        <h2 class="mb-0.5!">Basic Information</h2>
        <p class="text-muted text-[.88rem] m-0">Update your event's core details and delivery format.</p>
      </div>

      <div class="flex flex-col gap-4">
        <AppInput
          v-model="form.name"
          label="Event Name"
          placeholder="Enter Event Name"
          required
        />

        <div class="grid grid-cols-2 gap-3">
          <AppSelect
            v-model="form.sector"
            label="Event Sector"
            placeholder="Select Event Sector"
            :options="SECTORS"
          />
          <AppSelect
            v-model="form.timezone"
            label="Time Zone"
            required
          >
            <option v-for="z in zones" :key="z" :value="z">{{ z }}</option>
          </AppSelect>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <AppInput
            v-model="form.starts_at"
            label="Event Start"
            type="datetime-local"
            required
          />
          <AppInput
            v-model="form.ends_at"
            label="Event End"
            type="datetime-local"
            required
          />
        </div>

        <FormField label="Event Description">
          <SessionDescriptionEditor
            v-model="form.description"
            :toolbar="['bold', 'italic', 'underline', 'bulletList', 'orderedList', 'link']"
          />
        </FormField>
      </div>

      <hr class="border-line my-6">

      <!-- ── Event Type ──────────────────────────────── -->
      <h2 class="mb-3!">Event Type</h2>
      <div class="grid grid-cols-3 gap-3">
        <button
          v-for="d in DELIVERY" :key="d.value" type="button"
          class="relative flex items-center gap-2.5 px-4 py-3.5 rounded-xl border-[1.5px] cursor-pointer transition-all duration-150"
          :class="form.format === d.value
            ? 'border-brand bg-brand-soft text-brand'
            : 'border-line bg-white text-muted hover:border-[#c7c2f5] hover:text-brand'"
          @click="form.format = d.value"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="shrink-0">
            <path v-for="(p, i) in d.icon.split(' M').map((s, i) => (i ? 'M' + s : s))" :key="i" :d="p" />
          </svg>
          <span class="font-semibold text-[.9rem]">{{ d.label }}</span>
          <span
            v-if="form.format === d.value"
            class="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-[#22c55e] text-white grid place-items-center"
          >
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
          </span>
        </button>
      </div>

      <hr class="border-line my-6">

      <!-- ── Location ──────────────────────────────── -->
      <h2 class="mb-0.5!">Location</h2>
      <p class="text-muted text-[.88rem] mb-4">Let your participants know where to show up.</p>

      <div v-if="showAddress" class="flex flex-col gap-3">
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.venue" label="Venue" placeholder="Enter Venue" />
          <AppInput v-model="form.street" label="Street" placeholder="Enter Street" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.address_line_1" label="Address Line 1" placeholder="Enter Address Line 1" />
          <AppInput v-model="form.address_line_2" label="Address Line 2" placeholder="Enter Address Line 2" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppSelect v-model="form.country" label="Country" placeholder="Select Country" :options="COUNTRIES" />
          <AppInput v-model="form.state" label="State" placeholder="Enter State" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <AppInput v-model="form.city" label="City" placeholder="Enter City" />
          <AppInput v-model="form.zip" label="Zip" placeholder="Enter Zip" />
        </div>
      </div>

      <AppInput
        v-if="showUrl"
        v-model="form.url"
        label="Online Link"
        placeholder="https://… (meeting or stream URL)"
      >
        <template #prefix>
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
          </svg>
        </template>
      </AppInput>

      <!-- Error -->
      <p v-if="error" class="error mt-4">{{ error }}</p>

      <div class="flex items-center gap-3 mt-6">
        <button class="btn" :disabled="!form.name || saving" @click="save">
          <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
            <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
          </svg>
          <svg v-else-if="saved" width="15" height="15" viewBox="0 0 24 24" fill="none">
            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          {{ saving ? 'Saving…' : saved ? 'Saved' : 'Save' }}
        </button>
        <button class="btn ghost" :disabled="saving" @click="cancel">Cancel</button>
      </div>
    </div>

  </div>
</template>

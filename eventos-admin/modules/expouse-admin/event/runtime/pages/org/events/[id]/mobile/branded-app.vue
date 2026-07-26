<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface BrandedApp {
  enabled: boolean
  app_name: string
  tagline: string
  icon_file_id: number | null
  icon_url: string | null
  splash_file_id: number | null
  splash_url: string | null
  primary_color: string
  ios_url: string
  android_url: string
}

const form = reactive<BrandedApp>({
  enabled: false,
  app_name: '',
  tagline: '',
  icon_file_id: null,
  icon_url: null,
  splash_file_id: null,
  splash_url: null,
  primary_color: '#6352e7',
  ios_url: '',
  android_url: '',
})

const saving = ref(false)
const loading = ref(true)

function hydrate(b: any) {
  Object.assign(form, {
    enabled: b?.enabled ?? false,
    app_name: b?.app_name ?? '',
    tagline: b?.tagline ?? '',
    icon_file_id: b?.icon_file_id ?? null,
    icon_url: b?.icon_url ?? null,
    splash_file_id: b?.splash_file_id ?? null,
    splash_url: b?.splash_url ?? null,
    primary_color: b?.primary_color || '#6352e7',
    ios_url: b?.ios_url ?? '',
    android_url: b?.android_url ?? '',
  })
}

async function load() {
  loading.value = true
  try {
    const res = await api<any>(`/events/${id}/settings`)
    hydrate(res.data?.branded_app || {})
  } catch { /* */ }
  finally { loading.value = false }
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, {
      method: 'PUT',
      body: {
        branded_app: {
          enabled: form.enabled,
          app_name: form.app_name.trim() || null,
          tagline: form.tagline.trim() || null,
          icon_file_id: form.icon_file_id,
          icon_url: form.icon_url,
          splash_file_id: form.splash_file_id,
          splash_url: form.splash_url,
          primary_color: form.primary_color,
          ios_url: form.ios_url.trim() || null,
          android_url: form.android_url.trim() || null,
        },
      },
    })
    toast.success('Branded app saved')
  } catch (e: any) {
    toast.error(e?.data?.message || 'Could not save.')
  } finally { saving.value = false }
}

onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Branded Mobile App</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Configure your white-labelled app identity — icon, splash, colours and store links.
      </p>
    </div>

    <div class="card">
      <div class="flex items-start justify-between gap-4 mb-5">
        <div>
          <div class="font-bold text-base">Enable branded app</div>
          <div class="muted text-[.84rem]">Publish this event under your own branded mobile app.</div>
        </div>
        <AppCheckbox v-model="form.enabled" />
      </div>

      <div class="grid gap-5 md:grid-cols-2 border-t border-line pt-5">
        <div>
          <AppInput v-model="form.app_name" label="App name" placeholder="e.g. Expouse Lead" />
        </div>
        <div>
          <AppInput v-model="form.tagline" label="Tagline" placeholder="e.g. Lead Capture & Insights App" />
        </div>
      </div>

      <div class="grid gap-5 md:grid-cols-2 mt-5">
        <FormField label="App icon" hint="Square image (1:1), 512×512 px recommended.">
          <ImageField
            :model-value="form.icon_url"
            :aspect="1"
            :output-width="512"
            :output-height="512"
            collection="app_icon"
            card-width="120px"
            :gallery-path="`/events/${id}/gallery`"
            @update:model-value="form.icon_url = (Array.isArray($event) ? $event[0] : $event) || null"
            @uploaded="(v: any) => form.icon_file_id = v.id"
          />
        </FormField>

        <FormField label="Splash / launch image" hint="Portrait phone image (9:16).">
          <ImageField
            :model-value="form.splash_url"
            :aspect="9 / 16"
            :output-width="720"
            :output-height="1280"
            collection="app_splash"
            card-width="120px"
            :gallery-path="`/events/${id}/gallery`"
            @update:model-value="form.splash_url = (Array.isArray($event) ? $event[0] : $event) || null"
            @uploaded="(v: any) => form.splash_file_id = v.id"
          />
        </FormField>
      </div>

      <div class="mt-5 border-t border-line pt-5">
        <FormField label="Primary colour">
          <div class="flex items-center gap-3">
            <input
              type="color"
              v-model="form.primary_color"
              class="w-10 h-10 p-0 border border-line rounded-lg cursor-pointer bg-white"
            >
            <AppInput v-model="form.primary_color" class="w-32" placeholder="#6352e7" />
          </div>
        </FormField>
      </div>

      <div class="grid gap-5 md:grid-cols-2 mt-5 border-t border-line pt-5">
        <div>
          <AppInput v-model="form.ios_url" label="App Store URL (iOS)" placeholder="https://apps.apple.com/…" />
        </div>
        <div>
          <AppInput v-model="form.android_url" label="Google Play URL (Android)" placeholder="https://play.google.com/…" />
        </div>
      </div>

      <div class="border-t border-line mt-6 pt-4 flex justify-end">
        <button class="btn px-8 py-3 tracking-widest" :disabled="saving || loading" @click="save">
          {{ saving ? 'SAVING…' : 'SAVE' }}
        </button>
      </div>
    </div>
  </div>
</template>

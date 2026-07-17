<script setup lang="ts">
import { ref, computed } from 'vue'

const props = defineProps<{
  video: {
    type:             string
    url:              string
    show_after_login: boolean
    show_on_home:     boolean
  }
}>()

const emit = defineEmits<{
  (e: 'save'): void
}>()

const open = ref(false)

const VIDEO_TYPES = [
  { value: 'youtube',  label: 'YouTube',  icon: 'M15 12l-5 3.5V8.5L15 12z M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10z' },
  { value: 'vimeo',    label: 'Vimeo',    icon: 'M21 7.5c-.1 2.1-1.5 5-4.3 8.6C14 20 11.5 21.7 9.5 21.7c-1.3 0-2.4-1.2-3.3-3.6L4.7 12.6C4 10.2 3.3 9 2.5 9c-.2 0-.8.3-1.9 1l-1.2-1.5c1.2-1.1 2.4-2.1 3.5-3.1 1.6-1.4 2.8-2.1 3.6-2.2 1.9-.2 3 1.1 3.5 3.9.5 2.9.8 4.7 1 5.4.6 2.5 1.2 3.7 1.9 3.7.5 0 1.3-.8 2.4-2.5 1-1.6 1.6-2.8 1.7-3.7.1-1.4-.4-2.1-1.7-2.1-.6 0-1.2.1-1.8.4.9-3.3 2.7-4.9 5.4-4.8 2 0 2.9 1.3 2.8 3.9z' },
  { value: 'uploaded', label: 'Uploaded', icon: 'M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z M14 2v6h6 M12 18v-6 M9 15l3-3 3 3' },
]

function labelFor(type: string) {
  if (type === 'youtube') return 'YouTube URL'
  if (type === 'vimeo')   return 'Vimeo URL'
  return 'Video URL'
}

function pathsFor(icon: string) {
  return icon.split(' M').map((s, i) => (i ? 'M' + s : s))
}

function youtubeId(url: string): string | null {
  const m = url.match(/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/)
  return m ? m[1] : null
}

function vimeoId(url: string): string | null {
  const m = url.match(/vimeo\.com\/(?:video\/)?(\d+)/)
  return m ? m[1] : null
}

const embedUrl = computed<string | null>(() => {
  if (!props.video.url) return null
  if (props.video.type === 'youtube') {
    const id = youtubeId(props.video.url)
    return id ? `https://www.youtube.com/embed/${id}?rel=0&modestbranding=1` : null
  }
  if (props.video.type === 'vimeo') {
    const id = vimeoId(props.video.url)
    return id ? `https://player.vimeo.com/video/${id}` : null
  }
  return props.video.url || null
})

const thumbnailUrl = computed<string | null>(() => {
  if (props.video.type === 'youtube' && props.video.url) {
    const id = youtubeId(props.video.url)
    return id ? `https://img.youtube.com/vi/${id}/hqdefault.jpg` : null
  }
  return null
})

const hasValidPreview = computed(() => !!embedUrl.value || !!thumbnailUrl.value)
</script>

<template>
  <!-- Section row -->
  <div class="px-5 py-5">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-xl bg-brand-soft grid place-items-center shrink-0">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-brand">
          <path d="M15 12l-5 3.5V8.5L15 12z M22 12c0 5.523-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2s10 4.477 10 10z"/>
        </svg>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-semibold text-[.95rem] text-ink mb-0.5">Welcome Video</p>
        <p class="text-[.82rem] text-muted">Greet attendees with a welcome video after login or on the home screen.</p>
      </div>
      <button class="btn ghost shrink-0" @click="open = true">
        Manage
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M9 18l6-6-6-6"/>
        </svg>
      </button>
    </div>

    <!-- Card preview -->
    <div class="mt-3 pt-3 border-t border-line">
      <!-- YouTube thumbnail preview -->
      <div v-if="thumbnailUrl" class="relative rounded-xl overflow-hidden bg-black" style="max-width:420px;aspect-ratio:16/9">
        <img :src="thumbnailUrl" alt="Video thumbnail" class="w-full h-full object-cover opacity-90">
        <!-- Play button overlay -->
        <div class="absolute inset-0 flex items-center justify-center">
          <div class="w-12 h-12 rounded-full bg-black/60 grid place-items-center backdrop-blur-sm shadow-lg">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
              <path d="M8 5v14l11-7z"/>
            </svg>
          </div>
        </div>
        <!-- Type badge -->
        <span class="absolute top-2 left-2 px-2 py-0.5 rounded-md bg-black/60 text-white text-[.75rem] font-semibold backdrop-blur-sm capitalize">
          {{ video.type }}
        </span>
      </div>

      <!-- Vimeo / uploaded: compact embed iframe -->
      <div v-else-if="embedUrl && video.type !== 'youtube'" class="rounded-xl overflow-hidden border border-line" style="max-width:420px;aspect-ratio:16/9">
        <iframe
          v-if="video.type === 'vimeo'"
          :src="embedUrl"
          class="w-full h-full"
          frameborder="0"
          allow="autoplay; fullscreen; picture-in-picture"
          allowfullscreen
        />
        <video
          v-else
          :src="embedUrl"
          class="w-full h-full object-cover"
          controls
          preload="metadata"
        />
      </div>

      <!-- Not configured -->
      <div v-else class="flex items-center gap-2">
        <span class="badge">Not configured</span>
        <span class="text-[.82rem] text-muted">Add a URL to preview the video here.</span>
      </div>

      <!-- Status row when active -->
      <div v-if="video.url" class="flex items-center gap-2 mt-2">
        <span class="badge active">Active</span>
        <span class="text-[.8rem] text-muted truncate max-w-[320px]">{{ video.url }}</span>
      </div>
    </div>
  </div>

  <!-- Drawer -->
  <Drawer v-if="open" title="Manage Welcome Video" @close="open = false">

    <!-- Video type selector -->
    <div class="mb-5">
      <label class="block mb-2">Video type</label>
      <div class="flex gap-2">
        <button
          v-for="t in VIDEO_TYPES" :key="t.value" type="button"
          class="flex-1 flex flex-col items-center gap-1.5 py-3 rounded-xl border-[1.5px] cursor-pointer transition-all duration-150 text-[.82rem] font-semibold"
          :class="video.type === t.value
            ? 'border-brand bg-brand-soft text-brand'
            : 'border-line bg-white text-muted hover:border-[#c7c2f5] hover:text-brand'"
          @click="video.type = t.value"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path v-for="(p, i) in pathsFor(t.icon)" :key="i" :d="p" />
          </svg>
          {{ t.label }}
        </button>
      </div>
    </div>

    <!-- URL input -->
    <AppInput
      v-model="video.url"
      :label="labelFor(video.type)"
      placeholder="https://…"
      class="mb-4"
    />

    <!-- Inline preview -->
    <div v-if="hasValidPreview" class="mb-5">
      <p class="text-[.82rem] font-semibold text-muted mb-2 uppercase tracking-wide">Preview</p>
      <div class="rounded-xl overflow-hidden border border-line bg-black relative" style="aspect-ratio:16/9">
        <!-- YouTube thumbnail + play -->
        <template v-if="thumbnailUrl">
          <img :src="thumbnailUrl" alt="Video preview" class="w-full h-full object-cover opacity-90">
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="w-14 h-14 rounded-full bg-black/60 grid place-items-center backdrop-blur-sm shadow-xl">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="white">
                <path d="M8 5v14l11-7z"/>
              </svg>
            </div>
          </div>
        </template>
        <!-- Vimeo iframe -->
        <iframe
          v-else-if="video.type === 'vimeo' && embedUrl"
          :src="embedUrl"
          class="w-full h-full"
          frameborder="0"
          allow="autoplay; fullscreen; picture-in-picture"
          allowfullscreen
        />
        <!-- Uploaded video -->
        <video
          v-else-if="video.type === 'uploaded' && embedUrl"
          :src="embedUrl"
          class="w-full h-full object-cover"
          controls
          preload="metadata"
        />
      </div>
    </div>

    <!-- Settings toggles -->
    <div class="mb-5">
      <p class="text-[.88rem] font-semibold text-ink mb-2">Settings</p>
      <div class="rounded-xl border border-line divide-y divide-line overflow-hidden">
        <div class="px-4">
          <NavigationToggleSwitch
            v-model="video.show_after_login"
            label="Show after login"
          />
        </div>
        <div class="px-4">
          <NavigationToggleSwitch
            v-model="video.show_on_home"
            label="Show on home screen"
          />
        </div>
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn ghost" @click="open = false">Cancel</button>
      <button class="btn" @click="emit('save'); open = false">Save</button>
    </div>
  </Drawer>
</template>

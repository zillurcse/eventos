<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface ExpoLensPhoto {
  id: string
  url: string
  caption: string | null
  album: string
  processing_status: string
  moderation_status: string
  faces_count: number
  matches_count?: number
  created_at: string | null
}

const photos = ref<ExpoLensPhoto[]>([])
const loading = ref(true)
const tab = ref<'pending' | 'approved' | 'rejected'>('pending')

const filtered = computed(() => photos.value.filter(p => p.moderation_status === tab.value))

async function load() {
  loading.value = true
  try {
    const res = await api<{ data: ExpoLensPhoto[] }>(`/events/${id}/expolens/photos`, {
      query: { per_page: 100, moderation_status: tab.value },
    })
    photos.value = res.data
  } catch {
    toast.error('Could not load uploads.')
  } finally {
    loading.value = false
  }
}

async function setStatus(photo: ExpoLensPhoto, moderation_status: 'approved' | 'rejected' | 'pending') {
  try {
    const res = await api<{ data: ExpoLensPhoto }>(`/events/${id}/expolens/photos/${photo.id}`, {
      method: 'PATCH',
      body: { moderation_status },
    })
    photos.value = photos.value.filter(p => p.id !== photo.id)
    if (res.data.moderation_status === tab.value) {
      photos.value.unshift(res.data)
    }
    toast.success(`Marked ${moderation_status}`)
  } catch {
    toast.error('Could not update moderation status.')
  }
}

async function removePhoto(photo: ExpoLensPhoto) {
  if (!confirm('Delete this upload permanently?')) return
  try {
    await api(`/events/${id}/expolens/photos/${photo.id}`, { method: 'DELETE' })
    photos.value = photos.value.filter(p => p.id !== photo.id)
  } catch {
    toast.error('Could not delete upload.')
  }
}

watch(tab, load)
onMounted(load)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Moderate Uploads</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Approve photos before they appear in the public ExpoLens gallery. Matched attendees can still see approved photos in My Photos.
      </p>
    </div>

    <div class="flex flex-wrap gap-2 mb-4">
      <button
        v-for="t in (['pending', 'approved', 'rejected'] as const)"
        :key="t"
        class="px-3.5 py-1.5 rounded-full border text-sm font-medium capitalize"
        :class="tab === t
          ? 'border-[#6352e7] bg-[#f3f0ff] text-[#6352e7]'
          : 'border-line bg-white text-muted'"
        @click="tab = t"
      >
        {{ t }}
      </button>
    </div>

    <div v-if="loading" class="muted text-center py-10">Loading…</div>

    <div v-else-if="filtered.length" class="grid gap-3">
      <div
        v-for="photo in filtered"
        :key="photo.id"
        class="flex gap-4 p-3 border border-line rounded-xl bg-white"
      >
        <img :src="photo.url" class="w-28 h-28 rounded-lg object-cover shrink-0" alt="">
        <div class="min-w-0 flex-1">
          <div class="font-medium truncate">{{ photo.caption || photo.album || 'Untitled photo' }}</div>
          <div class="muted text-sm mt-1">
            {{ photo.processing_status }} · {{ photo.faces_count }} faces
            <span v-if="photo.matches_count != null"> · {{ photo.matches_count }} matches</span>
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <button
              v-if="photo.moderation_status !== 'approved'"
              class="btn"
              @click="setStatus(photo, 'approved')"
            >
              Approve
            </button>
            <button
              v-if="photo.moderation_status !== 'rejected'"
              class="btn ghost"
              @click="setStatus(photo, 'rejected')"
            >
              Reject
            </button>
            <button
              v-if="photo.moderation_status !== 'pending'"
              class="btn ghost"
              @click="setStatus(photo, 'pending')"
            >
              Move to pending
            </button>
            <button class="btn ghost" @click="removePhoto(photo)">Delete</button>
          </div>
        </div>
      </div>
    </div>

    <p v-else class="muted text-center py-10">No {{ tab }} uploads.</p>
  </div>
</template>

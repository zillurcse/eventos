<script setup lang="ts">
import { toast } from 'vue-sonner'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

interface AttendeeRow {
  id: string
  name: string | null
  email: string | null
  avatar_url: string | null
  matches_count: number
}

interface ExpoLensPhoto {
  id: string
  url: string
  caption: string | null
  album: string
  faces_count: number
  matches_count?: number
}

const search = ref('')
const loading = ref(false)
const attendees = ref<AttendeeRow[]>([])
const selected = ref<AttendeeRow | null>(null)
const photos = ref<ExpoLensPhoto[]>([])
const photosLoading = ref(false)

let timer: ReturnType<typeof setTimeout> | null = null

async function loadAttendees() {
  loading.value = true
  try {
    const res = await api<{ data: AttendeeRow[] }>(`/events/${id}/expolens/attendees`, {
      query: { search: search.value || undefined },
    })
    attendees.value = res.data
  } catch {
    toast.error('Could not search attendees.')
  } finally {
    loading.value = false
  }
}

function onSearchInput() {
  if (timer) clearTimeout(timer)
  timer = setTimeout(loadAttendees, 250)
}

async function selectAttendee(row: AttendeeRow) {
  selected.value = row
  photosLoading.value = true
  try {
    const res = await api<{ data: ExpoLensPhoto[] }>(`/events/${id}/expolens/attendees/${row.id}`, {
      query: { per_page: 60 },
    })
    photos.value = res.data
  } catch {
    toast.error('Could not load matched photos.')
    photos.value = []
  } finally {
    photosLoading.value = false
  }
}

onMounted(loadAttendees)
</script>

<template>
  <div>
    <div class="mb-4">
      <h2 class="section-title m-0">Find Attendee Photos</h2>
      <p class="muted text-[.86rem] mt-0.5 mb-0">
        Search an attendee and review every ExpoLens photo where their face was matched.
      </p>
    </div>

    <div class="grid lg:grid-cols-[320px_1fr] gap-4">
      <div class="border border-line rounded-xl bg-white p-3">
        <input
          v-model="search"
          class="w-full mb-3"
          placeholder="Search name or email…"
          @input="onSearchInput"
        >
        <div v-if="loading" class="muted text-sm py-6 text-center">Searching…</div>
        <ul v-else class="space-y-1 max-h-[70vh] overflow-auto">
          <li
            v-for="row in attendees"
            :key="row.id"
            class="flex items-center gap-3 p-2 rounded-lg cursor-pointer"
            :class="selected?.id === row.id ? 'bg-[#f3f0ff]' : 'hover:bg-[#f8f8fb]'"
            @click="selectAttendee(row)"
          >
            <img
              v-if="row.avatar_url"
              :src="row.avatar_url"
              class="w-9 h-9 rounded-full object-cover"
              alt=""
            >
            <div v-else class="w-9 h-9 rounded-full bg-[#eee] grid place-items-center text-xs">?</div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium truncate">{{ row.name || 'Attendee' }}</div>
              <div class="muted text-xs truncate">{{ row.email }}</div>
            </div>
            <div class="text-xs font-semibold text-[#6352e7]">{{ row.matches_count }}</div>
          </li>
          <li v-if="!attendees.length" class="muted text-sm py-6 text-center">No attendees found.</li>
        </ul>
      </div>

      <div class="border border-line rounded-xl bg-white p-4 min-h-[420px]">
        <div v-if="!selected" class="muted text-center py-20">
          Select an attendee to see their matched photos.
        </div>
        <template v-else>
          <div class="flex items-center gap-3 mb-4">
            <img
              v-if="selected.avatar_url"
              :src="selected.avatar_url"
              class="w-12 h-12 rounded-full object-cover"
              alt=""
            >
            <div>
              <div class="font-semibold">{{ selected.name }}</div>
              <div class="muted text-sm">{{ selected.email }} · {{ selected.matches_count }} matches</div>
            </div>
          </div>

          <div v-if="photosLoading" class="muted text-center py-10">Loading photos…</div>
          <div v-else-if="photos.length" class="grid grid-cols-[repeat(auto-fill,minmax(160px,1fr))] gap-3">
            <a
              v-for="photo in photos"
              :key="photo.id"
              :href="photo.url"
              target="_blank"
              rel="noopener"
              class="img-card aspect-square block"
            >
              <img :src="photo.url" :alt="photo.caption || ''" loading="lazy">
            </a>
          </div>
          <p v-else class="muted text-center py-10">No matched photos for this attendee yet.</p>
        </template>
      </div>
    </div>
  </div>
</template>

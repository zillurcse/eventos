<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

interface Track { id: number; name: string; color: string }
interface SessionSpeaker { id: string; name: string; image_url?: string | null }

interface Session {
  id: string
  title: string
  starts_at: string | null
  ends_at: string | null
  timezone?: string | null
  status: 'scheduled' | 'live' | 'ended' | 'canceled'
  session_place: string | null
  is_featured: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

interface Group { key: string; label: string; items: Session[] }

defineProps<{
  groups: Group[]
}>()

const emit = defineEmits<{
  (e: 'edit', session: Session): void
  (e: 'toggle-status', session: Session): void
  (e: 'remove', session: Session): void
}>()

const STATUS_BORDER: Record<string, string> = {
  scheduled: 'border-l-blue-400',
  live:      'border-l-green-500',
  ended:     'border-l-[#ccc]',
  canceled:  'border-l-red-400',
}

const STATUS_DOT: Record<string, string> = {
  scheduled: 'bg-blue-400',
  live:      'bg-green-500',
  ended:     'bg-[#ccc]',
  canceled:  'bg-red-400',
}

function fmtTime(iso: string | null, tz?: string | null): string {
  return tzTimeLabel(iso, tz || 'UTC')
}

function duration(s: Session): string {
  if (!s.starts_at || !s.ends_at) return ''
  const mins = Math.round(
    (new Date(s.ends_at).getTime() - new Date(s.starts_at).getTime()) / 60000,
  )
  if (mins <= 0) return ''
  if (mins < 60) return `${mins}m`
  const h = Math.floor(mins / 60), m = mins % 60
  return m ? `${h}h ${m}m` : `${h}h`
}

function initials(name: string | null | undefined): string {
  if (!name) return '?'
  return name.split(' ').slice(0, 2).map(w => w[0] ?? '').join('').toUpperCase()
}

// ── Three-dot menu (self-contained: closes on outside click) ──────────────────

const root       = ref<HTMLElement | null>(null)
const openMenuId = ref<string | null>(null)

function toggleMenu(sessionId: string, e: Event) {
  e.stopPropagation()
  openMenuId.value = openMenuId.value === sessionId ? null : sessionId
}

function onDocClick(e: MouseEvent) {
  if (openMenuId.value && root.value && !root.value.contains(e.target as Node)) {
    openMenuId.value = null
  }
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))

function edit(s: Session) { openMenuId.value = null; emit('edit', s) }
function toggleStatus(s: Session) { openMenuId.value = null; emit('toggle-status', s) }
function remove(s: Session) { openMenuId.value = null; emit('remove', s) }
</script>

<template>
  <div ref="root">
    <div v-for="group in groups" :key="group.key" class="mb-8">
      <!-- Day heading -->
      <div class="flex items-center gap-3 mb-3">
        <span class="font-bold text-[.78rem] tracking-widest uppercase text-muted">{{ group.label }}</span>
        <div class="flex-1 h-px bg-line" />
        <span class="text-[.78rem] text-muted font-medium">
          {{ group.items.length }} session{{ group.items.length !== 1 ? 's' : '' }}
        </span>
      </div>

      <!-- Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="s in group.items"
          :key="s.id"
          class="card p-0 overflow-hidden border-l-4 relative"
          :class="STATUS_BORDER[s.status] ?? 'border-l-[#ccc]'"
        >
          <!-- Three-dot trigger -->
          <button
            class="absolute top-3 right-3 w-7 h-7 flex items-center justify-center rounded-lg text-muted hover:bg-[#f1f1f5] text-[1.2rem] leading-none transition-colors z-10"
            @click="toggleMenu(s.id, $event)"
          >⋮</button>

          <!-- Dropdown menu -->
          <div
            v-if="openMenuId === s.id"
            class="absolute top-11 right-3 z-20 bg-white border border-line rounded-xl shadow-lg py-1 min-w-[168px]"
            @click.stop
          >
            <button
              class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
              @click="edit(s)"
            >Edit Session</button>
            <button
              class="w-full text-left px-4 py-2 text-[.88rem] hover:bg-[#f7f7fb]"
              @click="toggleStatus(s)"
            >
              Mark as
              {{ s.status === 'scheduled' ? 'Live' : s.status === 'live' ? 'Ended' : 'Scheduled' }}
            </button>
            <div class="border-t border-line my-1" />
            <button
              class="w-full text-left px-4 py-2 text-[.88rem] text-[#dc2626] hover:bg-red-50"
              @click="remove(s)"
            >Delete</button>
          </div>

          <!-- Card body -->
          <div class="p-4 pr-10">
            <!-- Time range + duration -->
            <div class="flex items-center gap-2 mb-2 flex-wrap">
              <span class="text-[.82rem] font-semibold text-muted">
                {{ s.starts_at ? fmtTime(s.starts_at, s.timezone) : 'TBD' }}
                <template v-if="s.ends_at"> — {{ fmtTime(s.ends_at, s.timezone) }}</template>
              </span>
              <span
                v-if="duration(s)"
                class="px-1.5 py-0.5 bg-brand-soft text-brand rounded text-[.7rem] font-semibold"
              >{{ duration(s) }}</span>
            </div>

            <!-- Title -->
            <h3 class="font-bold text-[.95rem] text-ink leading-snug mb-1 line-clamp-2 m-0">
              {{ s.title }}
            </h3>

            <!-- Place -->
            <p v-if="s.session_place" class="text-[.8rem] text-muted mb-1">
              📍 {{ s.session_place }}
            </p>

            <!-- Badges -->
            <div class="flex flex-wrap gap-1.5 mt-2">
              <span
                v-if="s.track"
                class="px-2 py-0.5 rounded-full text-[.7rem] font-medium text-white"
                :style="{ background: s.track.color || '#6352e7' }"
              >{{ s.track.name }}</span>
              <span class="flex items-center gap-1 px-2 py-0.5 rounded-full text-[.7rem] font-medium bg-[#f1f1f5] text-muted capitalize">
                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="STATUS_DOT[s.status]" />
                {{ s.status }}
              </span>
              <span
                v-if="s.is_featured"
                class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[.7rem] font-medium"
              >Featured</span>
            </div>

            <!-- Speaker avatars -->
            <div v-if="s.speakers?.length" class="flex items-center mt-3">
              <div
                v-for="sp in s.speakers.slice(0, 4)"
                :key="sp.id"
                class="w-6 h-6 rounded-full overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[.6rem] font-bold shrink-0 border-2 border-white -ml-1 first:ml-0"
                :title="sp.name"
              >
                <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
                <span v-else>{{ initials(sp.name) }}</span>
              </div>
              <span v-if="s.speakers.length > 4" class="text-[.73rem] text-muted ml-1.5">
                +{{ s.speakers.length - 4 }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

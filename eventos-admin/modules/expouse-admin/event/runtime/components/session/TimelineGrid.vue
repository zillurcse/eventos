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
  stream_url: string | null
  session_place: string | null
  is_featured: boolean
  track: Track | null
  speakers: SessionSpeaker[]
}

interface Row { key: string; timeLabel: string; sessions: Session[] }

defineProps<{
  rows: Row[]
}>()

const emit = defineEmits<{
  (e: 'edit', session: Session): void
  (e: 'toggle-status', session: Session): void
  (e: 'remove', session: Session): void
  (e: 'add', timeKey: string): void
}>()

const STATUS_COLOR: Record<string, string> = {
  scheduled: '#6366f1',
  live:      '#22c55e',
  ended:     '#ccc',
  canceled:  '#ef4444',
}

function fmtTime(iso: string | null, tz?: string | null): string {
  return tzTimeLabel(iso, tz || 'UTC')
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
function remove(s: Session) {
  openMenuId.value = null
  if (!confirm(`Delete session "${s.title}"?`)) return
  emit('remove', s)
}
</script>

<template>
  <div ref="root">
    <div
      v-for="row in rows"
      :key="row.key"
      class="flex items-start gap-4 py-4 border-b border-line last:border-b-0"
    >
      <!-- Time label -->
      <div class="w-20 shrink-0 pt-3 text-[.82rem] font-semibold text-muted">
        {{ row.timeLabel }}
      </div>

      <!-- Session cards for this slot -->
      <div class="flex-1 flex flex-wrap gap-3">
        <div
          v-for="s in row.sessions"
          :key="s.id"
          class="w-[270px] shrink-0 bg-white border border-line rounded-xl p-3.5 relative"
        >
          <!-- Three-dot trigger -->
          <button
            class="absolute top-2.5 right-2.5 w-6 h-6 flex items-center justify-center rounded-lg text-muted hover:bg-[#f1f1f5] text-[1.1rem] leading-none transition-colors z-10"
            @click="toggleMenu(s.id, $event)"
          >⋮</button>

          <!-- Dropdown menu -->
          <div
            v-if="openMenuId === s.id"
            class="absolute top-9 right-2.5 z-20 bg-white border border-line rounded-xl shadow-lg py-1 min-w-[160px]"
            @click.stop
          >
            <button class="w-full text-left px-4 py-2 text-[.85rem] hover:bg-[#f7f7fb]" @click="edit(s)">Edit Session</button>
            <button class="w-full text-left px-4 py-2 text-[.85rem] hover:bg-[#f7f7fb]" @click="toggleStatus(s)">
              Mark as {{ s.status === 'scheduled' ? 'Live' : s.status === 'live' ? 'Ended' : 'Scheduled' }}
            </button>
            <div class="border-t border-line my-1" />
            <button class="w-full text-left px-4 py-2 text-[.85rem] text-[#dc2626] hover:bg-red-50" @click="remove(s)">Delete</button>
          </div>

          <!-- Title -->
          <h4 class="font-bold text-[.86rem] text-ink leading-snug mb-1.5 pr-6 line-clamp-2 m-0 flex items-start gap-1.5">
            <span class="w-1 h-3.5 rounded-sm shrink-0 mt-[3px]" :style="{ background: s.track?.color || STATUS_COLOR[s.status] }" />
            {{ s.title }}
          </h4>

          <!-- Time range -->
          <p class="text-[.76rem] text-muted mb-2.5 ml-2.5">
            {{ s.starts_at ? fmtTime(s.starts_at, s.timezone) : 'TBD' }}
            <template v-if="s.ends_at"> - {{ fmtTime(s.ends_at, s.timezone) }}</template>
          </p>

          <!-- Bottom row: speaker avatars / icons -->
          <div class="flex items-center justify-between ml-2.5">
            <div v-if="s.speakers?.length" class="flex items-center">
              <div
                v-for="sp in s.speakers.slice(0, 4)"
                :key="sp.id"
                class="w-6 h-6 rounded-full overflow-hidden bg-brand-soft text-brand flex items-center justify-center text-[.6rem] font-bold shrink-0 border-2 border-white -ml-1 first:ml-0"
                :title="sp.name"
              >
                <img v-if="sp.image_url" :src="sp.image_url" :alt="sp.name" class="w-full h-full object-cover">
                <span v-else>{{ initials(sp.name) }}</span>
              </div>
              <span v-if="s.speakers.length > 4" class="text-[.7rem] text-muted ml-1.5">+{{ s.speakers.length - 4 }}</span>
            </div>
            <div v-else />

            <div class="flex items-center gap-2">
              <span v-if="s.stream_url" class="text-muted" title="Live stream attached">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="6" width="14" height="12" rx="2"/><path d="M16 10l6-3v10l-6-3"/></svg>
              </span>
              <button class="text-muted hover:text-[#dc2626] transition-colors" title="Delete session" @click="remove(s)">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Add-to-slot button -->
        <button
          class="w-11 h-11 shrink-0 rounded-xl bg-brand-soft text-brand flex items-center justify-center hover:bg-brand hover:text-white transition-colors"
          title="Add session at this time"
          @click="emit('add', row.key)"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </button>
      </div>
    </div>
  </div>
</template>

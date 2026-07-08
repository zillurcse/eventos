<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

const props = defineProps<{ delegate: Delegate }>()
const store = useDelegatesStore()
const chat = useChatStore()
const bookmarks = useBookmarksStore()

const bookmarked = computed(() => bookmarks.isOn('delegate', props.delegate.id))
const connectState = computed(() => store.connected[props.delegate.id])

// Touch devices have no hover — tapping the card pins the overlay instead.
const pinned = ref(false)

const contacting = ref(false)
async function contact() {
  if (contacting.value) return
  contacting.value = true
  try {
    if (!chat.drawerOpen) chat.toggleDrawer()
    await chat.openWith(props.delegate.id)
  } finally {
    contacting.value = false
  }
}

function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

const subtitle = computed(() => {
  const d = props.delegate
  return [d.job_title, d.company].filter(Boolean).join(' · ')
})
</script>

<template>
  <article class="card" :class="{ pinned }" @click="pinned = !pinned">
    <span
      class="status"
      :class="{ on: delegate.online }"
      :title="delegate.online ? 'Online' : 'Offline'"
    />

    <div class="avatar">
      <img v-if="delegate.avatar_url" :src="delegate.avatar_url" :alt="delegate.name || ''">
      <span v-else class="ini">{{ initials(delegate.name) }}</span>
    </div>

    <div class="body">
      <h3 class="name">{{ delegate.name }}</h3>
      <p v-if="subtitle" class="role">{{ subtitle }}</p>
    </div>

    <!-- Hover veil: quick actions on a dark scrim (mock: Contact + Save) -->
    <div class="veil" @click.stop>
      <div class="vacts">
        <button
          class="vact"
          :class="{ on: connectState === 'pending' }"
          type="button"
          :title="connectState === 'pending' ? 'Request sent' : 'Connect'"
          :disabled="connectState === 'pending'"
          @click="store.connect(delegate)"
        >
          <svg v-if="connectState === 'pending'" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
          <svg v-else viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" /></svg>
        </button>
        <button
          class="vact"
          :class="{ on: bookmarked }"
          type="button"
          :title="bookmarked ? 'Saved' : 'Save'"
          @click="bookmarks.toggle('delegate', delegate.id)"
        >
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4zM12 8v5M9.5 10.5h5" /></svg>
        </button>
      </div>

      <button class="contact" type="button" :disabled="contacting" @click="contact">
        <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
        {{ contacting ? 'Opening…' : 'Contact' }}
      </button>

      <div class="vinfo">
        <small v-if="subtitle">{{ subtitle }}</small>
        <strong>{{ delegate.name }}</strong>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card { position: relative; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); }

.status { position: absolute; top: 10px; right: 10px; z-index: 3; width: 11px; height: 11px; border-radius: 50%; background: #94a3b8; box-shadow: 0 0 0 2px #fff; }
.status.on { background: #22c55e; }

.avatar { position: relative; aspect-ratio: 1 / 1; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); display: flex; align-items: center; justify-content: center; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.ini { font-size: 3.4rem; font-weight: 700; color: color-mix(in srgb, var(--brand-primary) 75%, #fff); letter-spacing: 1px; }

.body { padding: 12px 14px 16px; text-align: center; }
.name { margin: 0; font-size: .96rem; font-weight: 700; color: var(--brand-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role { margin: 4px 0 0; color: #64748b; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Hover veil ── */
.veil { position: absolute; inset: 0; z-index: 2; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 14px; background: rgba(30,41,59,.82); opacity: 0; pointer-events: none; transition: opacity .18s ease; }
.card:hover .veil, .card.pinned .veil, .card:focus-within .veil { opacity: 1; pointer-events: auto; }

.vacts { position: absolute; top: 10px; right: 28px; display: flex; gap: 8px; }
.vact { width: 34px; height: 34px; border-radius: 10px; border: none; background: #fff; color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 1px 3px rgba(15,23,42,.25); }
.vact.on { background: var(--brand-primary); color: #fff; }
.vact:disabled { cursor: default; }
.vact svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.contact { display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 999px; padding: 11px 24px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .92rem; font-weight: 700; cursor: pointer; box-shadow: 0 6px 18px rgba(15,23,42,.35); }
.contact:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.contact:disabled { opacity: .7; cursor: default; }
.contact svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

.vinfo { display: flex; flex-direction: column; align-items: center; gap: 2px; min-width: 0; max-width: 100%; }
.vinfo small { color: rgba(255,255,255,.75); font-size: .76rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.vinfo strong { color: #fff; font-size: 1rem; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
</style>

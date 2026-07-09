<script setup lang="ts">
import type { Exhibitor } from '~/stores/exhibitors'

const props = defineProps<{ exhibitor: Exhibitor }>()
const store = useExhibitorsStore()
const contact = useExhibitorContactStore()

const bookmarks = useBookmarksStore()
const bookmarked = computed(() => bookmarks.isOn('exhibitor', props.exhibitor.id))

function toggleBookmark() {
  bookmarks.toggle('exhibitor', props.exhibitor.id)
}

function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

// Secondary meta line under the type: the event edition year.
const meta = computed(() => store.year ? String(store.year) : '')
</script>

<template>
  <article class="card" @click="navigateTo(`/exhibitor/${exhibitor.id}`)">
    <!-- Logo tile with a hover "Contact" veil -->
    <div class="logo">
      <img v-if="exhibitor.logo_url" :src="exhibitor.logo_url" :alt="exhibitor.name">
      <span v-else class="ini">{{ initials(exhibitor.name) }}</span>

      <div class="veil" @click.stop>
        <button class="contact" type="button" @click="contact.openFor({ id: exhibitor.id, name: exhibitor.name })">
          <svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z" /></svg>
          Contact
        </button>
      </div>
    </div>

    <div class="body">
      <h3 class="name">{{ exhibitor.name }}</h3>
      <p class="type">{{ exhibitor.type === 'sponsor' ? 'sponsor' : 'exhibitor' }}</p>
      <p v-if="meta" class="meta">{{ meta }}</p>

      <button
        class="bm"
        :class="{ on: bookmarked }"
        type="button"
        :title="bookmarked ? 'Saved' : 'Save'"
        @click.stop="toggleBookmark"
      >
        <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
      </button>
    </div>
  </article>
</template>

<style scoped>
.card { position: relative; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); cursor: pointer; transition: box-shadow .15s, transform .15s; }
.card:hover { box-shadow: 0 8px 24px rgba(15,23,42,.1); transform: translateY(-2px); }

/* ── Logo tile ── */
.logo { position: relative; height: 150px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid #eef0f3; }
.logo img { width: 100%; height: 100%; object-fit: cover; }
.ini { font-size: 2.6rem; font-weight: 800; color: color-mix(in srgb, var(--brand-primary) 55%, #cbd5e1); }

/* Hover veil scoped to the logo, with the Contact CTA */
.veil { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(30,41,59,.42); opacity: 0; pointer-events: none; transition: opacity .18s ease; }
.card:hover .veil, .card:focus-within .veil { opacity: 1; pointer-events: auto; }
.contact { display: inline-flex; align-items: center; gap: 8px; border: none; border-radius: 999px; padding: 10px 22px; background: var(--brand-primary); color: #fff; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; box-shadow: 0 6px 18px rgba(15,23,42,.35); }
.contact:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
.contact svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }

/* ── Body ── */
.body { position: relative; padding: 14px 46px 16px 16px; }
.name { margin: 0; font-size: 1rem; font-weight: 700; color: var(--brand-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.type { margin: 8px 0 0; font-size: .82rem; font-weight: 600; color: var(--brand-primary); text-transform: lowercase; }
.meta { margin: 2px 0 0; font-size: .82rem; color: #64748b; }

.bm { position: absolute; right: 12px; bottom: 14px; width: 30px; height: 30px; border-radius: 8px; border: none; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background .15s, color .15s; }
.bm svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.bm:hover { background: color-mix(in srgb, var(--brand-primary) 20%, #fff); }
.bm.on { background: var(--brand-primary); color: #fff; }
.bm.on svg { fill: currentColor; }
</style>

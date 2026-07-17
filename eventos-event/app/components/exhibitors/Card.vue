<script setup lang="ts">
import type { Exhibitor } from '~/stores/exhibitors'

const props = defineProps<{ exhibitor: Exhibitor }>()
const contact = useExhibitorContactStore()

const bookmarks = useBookmarksStore()
const bookmarked = computed(() => bookmarks.isOn('exhibitor', props.exhibitor.id))

function toggleBookmark() {
  bookmarks.toggle('exhibitor', props.exhibitor.id)
}

function openChat() {
  contact.openFor({ id: props.exhibitor.id, name: props.exhibitor.name }, 'chat')
}
function openMeet() {
  contact.openFor({ id: props.exhibitor.id, name: props.exhibitor.name }, 'meet')
}

// "10082, Exhibitor" — booth number (when set) alongside the booth type.
const meta = computed(() => {
  const label = props.exhibitor.type === 'sponsor' ? 'Sponsor' : 'Exhibitor'
  return props.exhibitor.booth ? `${props.exhibitor.booth}, ${label}` : label
})
</script>

<template>
  <article class="card" @click="navigateTo(`/exhibitor/${exhibitor.id}`)">
    <div class="photo">
      <AppImage :src="exhibitor.logo_url" :alt="exhibitor.name" />

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

    <div class="body">
      <div class="who">
        <div class="mark"><AppImage :src="exhibitor.logo_url" :alt="exhibitor.name" /></div>
        <div class="whotext">
          <h3 class="name">{{ exhibitor.name }}</h3>
          <p class="meta">{{ meta }}</p>
        </div>
      </div>

      <div class="actions" @click.stop>
        <button type="button" class="act" @click="openChat">
          <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></svg>
          Chat
        </button>
        <button type="button" class="act" @click="openMeet">
          <svg viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5V7z" /><rect x="1" y="5" width="15" height="14" rx="2" /></svg>
          Meet
        </button>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card { position: relative; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); cursor: pointer; transition: box-shadow .15s, transform .15s; }
.card:hover { box-shadow: 0 8px 24px rgba(15,23,42,.1); transform: translateY(-2px); }

/* ── Photo ── */
.photo { position: relative; height: 170px; background: #f1f5f9; overflow: hidden; }
.photo img { width: 100%; height: 100%; object-fit: cover; }

.bm { position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 9px; border: none; background: #fff; color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 2px 6px rgba(15,23,42,.18); transition: background .15s, color .15s; }
.bm svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.bm:hover { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); }
.bm.on { background: var(--brand-primary); color: #fff; }
.bm.on svg { fill: currentColor; }

/* ── Body ── */
.body { position: relative; padding: 12px 14px 14px; }
.who { display: flex; align-items: center; gap: 10px; }
.mark { flex: 0 0 auto; width: 38px; height: 38px; border-radius: 10px; background: var(--brand-primary); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.mark img { width: 100%; height: 100%; object-fit: cover; }
.whotext { min-width: 0; }
.name { margin: 0; font-size: .96rem; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.meta { margin: 2px 0 0; font-size: .8rem; color: #64748b; }

/* Overlaid on hover/focus so it never changes the card's own height or
   pushes neighboring cards in the grid row. */
.actions { position: absolute; left: 14px; right: 14px; bottom: 14px; display: flex; gap: 8px; opacity: 0; pointer-events: none; padding-top: 22px; background: linear-gradient(to top, #fff 65%, transparent); transition: opacity .15s ease; }
.card:hover .actions, .card:focus-within .actions { opacity: 1; pointer-events: auto; }
.act { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 7px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 9px 10px; background: #fff; color: #334155; font: inherit; font-size: .84rem; font-weight: 600; cursor: pointer; transition: background .15s, border-color .15s, color .15s; }
.act:hover { border-color: color-mix(in srgb, var(--brand-primary) 45%, #fff); color: var(--brand-primary); }
.act svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
</style>

<script setup lang="ts">
import type { Speaker } from '~/stores/speakers'

const props = defineProps<{ speaker: Speaker }>()
const store = useSpeakersStore()
const bookmarks = useBookmarksStore()
const auth = useAuthStore()

const bookmarked = computed(() => bookmarks.isOn('speaker', props.speaker.id))

const subtitle = computed(() => {
  const s = props.speaker
  return [s.designation, s.company].filter(Boolean).join(' · ')
})

const connectState = computed(() => store.connected[props.speaker.id])
</script>

<template>
  <article class="card" @click="store.open(speaker)">
    <div class="avatar">
      <UserAvatar :src="speaker.image_url" :name="speaker.name" />
    </div>

    <!-- Hover-revealed quick actions (space always reserved, no layout jump) -->
    <div class="acts reveal">
      <button
        v-if="auth.isAuthed"
        class="act connect"
        :class="{ on: connectState === 'pending' }"
        type="button"
        :title="connectState === 'pending' ? 'Request sent' : 'Connect'"
        :disabled="connectState === 'pending'"
        @click.stop="store.connect(speaker)"
      >
        <svg v-if="connectState === 'pending'" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5" /></svg>
        <svg v-else viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" /></svg>
      </button>
      <button
        class="act bm"
        :class="{ on: bookmarked }"
        type="button"
        :title="bookmarked ? 'Saved' : 'Save'"
        @click.stop="bookmarks.toggle('speaker', speaker.id)"
      >
        <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
      </button>
    </div>

    <div class="body">
      <h3 class="name">{{ speaker.name }}</h3>
      <p class="role">{{ subtitle || ' ' }}</p>
    </div>

    <button class="view reveal" type="button" @click.stop="store.open(speaker)">View Profile</button>
  </article>
</template>

<style scoped>
.card { display: flex; flex-direction: column; align-items: center; background: #fff; border-radius: 14px; padding: 18px 14px 16px; box-shadow: 0 1px 2px rgba(15,23,42,.05); cursor: pointer; transition: box-shadow .18s ease, transform .18s ease; }
.card:hover { box-shadow: 0 10px 26px rgba(15,23,42,.12); transform: translateY(-2px); }

.avatar { width: 128px; height: 128px; border-radius: 50%; overflow: hidden; background: color-mix(in srgb, var(--brand-primary) 10%, #fff); border: 3px solid color-mix(in srgb, var(--brand-primary) 16%, #fff); display: flex; align-items: center; justify-content: center; }
.avatar img { width: 100%; height: 100%; object-fit: cover; }

/* Elements that fade in on hover; space is always reserved. */
.reveal { opacity: 0; transition: opacity .18s ease; }
.card:hover .reveal, .card:focus-within .reveal { opacity: 1; }
@media (hover: none) { .reveal { opacity: 1; } }

.acts { display: flex; gap: 10px; margin-top: 12px; min-height: 38px; }
.act { width: 40px; height: 38px; border-radius: 12px; border: 1px solid transparent; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; }
.act svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.act.connect { background: var(--brand-primary); color: #fff; }
.act.connect.on { background: #16a34a; }
.act.connect:disabled { cursor: default; }
.act.bm { background: #fff; color: var(--brand-primary); border-color: #dfe3ea; }
.act.bm.on { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
.act.bm.on svg { fill: currentColor; }

.body { margin-top: 10px; text-align: center; min-width: 0; width: 100%; }
.name { margin: 0; font-size: .98rem; font-weight: 800; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.role { margin: 3px 0 0; color: #64748b; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.view { width: 100%; margin-top: 12px; border: none; border-radius: 8px; padding: 10px 0; background: var(--brand-primary); color: #fff; font: inherit; font-size: .8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; cursor: pointer; }
.view:hover { background: color-mix(in srgb, var(--brand-primary) 88%, #000); }
</style>

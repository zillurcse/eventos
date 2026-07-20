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

const meta = computed(() => {
  const label = props.exhibitor.type === 'sponsor' ? 'Sponsor' : 'Exhibitor'
  return props.exhibitor.booth ? `${props.exhibitor.booth}, ${label}` : label
})

const coverUrl = computed(() => {
  const product = props.exhibitor.products?.find(p => p.image_url)
  return product?.image_url || props.exhibitor.logo_url
})
</script>

<template>
  <article class="card" @click="navigateTo(`/exhibitor/${exhibitor.id}`)">
    <div class="photo">
      <AppImage :src="coverUrl" :alt="exhibitor.name" />

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
    </div>

    <!-- Hover-revealed actions: photo shrinks to free the space this area
         slides into (bottom to top), so the card height stays constant. -->
    <div class="reveal" @click.stop>
      <div class="reveal-inner">
        <div class="actions">
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
    </div>
  </article>
</template>

<style scoped>
.card {
  --photo-h: 150px;
  --reveal-h: 60px;
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  cursor: pointer;
  border: 1px solid #E8E8EE;
  transition: border-color .18s ease, box-shadow .18s ease;
  margin: 0;
  padding: 0;
}

.card:hover,
.card:focus-within {
  border-color: var(--brand-primary);
  box-shadow: 0 8px 24px rgba(15, 23, 42, .1);
}

.photo {
  position: relative;
  width: 100%;
  height: var(--photo-h);
  flex-shrink: 0;
  border-radius: 8px 8px 0 0;
  background: #f1f5f9;
  overflow: hidden;
  transition: height .22s ease;
}

.card:hover .photo,
.card:focus-within .photo {
  height: calc(var(--photo-h) - var(--reveal-h));
}


.photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.bm {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  border: none;
  background: #fff;
  color: var(--brand-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .15);
  transition: background .15s, color .15s;
}

.bm svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.bm.on {
  background: var(--brand-primary);
  color: #fff;
}

.bm.on svg {
  fill: currentColor;
}

.body {
  padding: 12px;
}

.who {
  display: flex;
  align-items: center;
  gap: 10px;
}

.mark {
  flex: 0 0 auto;
  width: 50px;
  height: 50px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.mark img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.whotext {
  min-width: 0;
}

.name {
  font-size: 18px;
  line-height: 24px;
  margin: 0;
  font-weight: 700;
  color: #1e293b;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.meta {
  margin: 1px 0 0;
  font-size: 14px;
  line-height: 20px;
  color: #64676A;
}

.reveal {
  height: 0;
  opacity: 0;
  overflow: hidden;
  flex-shrink: 0;
  transition: height .22s ease, opacity .18s ease;
}

.card:hover .reveal,
.card:focus-within .reveal {
  height: var(--reveal-h);
  opacity: 1;
}

.reveal-inner {
  padding: 0 12px 12px;
  transform: translateY(14px);
  transition: transform .22s ease;
}

.card:hover .reveal-inner,
.card:focus-within .reveal-inner {
  transform: translateY(0);
}

@media (hover: none) {
  .photo {
    height: calc(var(--photo-h) - var(--reveal-h));
  }

  .reveal {
    height: var(--reveal-h);
    opacity: 1;
  }

  .reveal-inner {
    transform: translateY(0);
  }
}

.actions {
  display: flex;
  gap: 10px;
  margin-bottom: 0;
}

.act {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  border: 1px solid var(--brand-primary);
  border-radius: 8px;
  padding: 9px 12px;
  background: #fff;
  color: var(--brand-primary);
  font: inherit;
  font-size: .84rem;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s, color .15s;
}

.act:hover {
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.act svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>

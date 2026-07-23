<script setup lang="ts">
import type { Delegate } from '~/stores/delegates'

const props = defineProps<{ delegate: Delegate }>()
const chat = useChatStore()
const bookmarks = useBookmarksStore()

const bookmarked = computed(() => bookmarks.isOn('delegate', props.delegate.id))

// Touch devices have no hover — tapping the card pins the reveal row instead.
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
</script>

<template>
  <article class="card" :class="{ pinned }" @click="pinned = !pinned">
    <div class="photo">
      <UserAvatar :src="delegate.avatar_url" :name="delegate.name" />
      <button class="bm" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Saved' : 'Save'"
        @click.stop="bookmarks.toggle('delegate', delegate.id)">
        <svg viewBox="0 0 24 24">
          <path d="M6 3h12v18l-6-4-6 4z" />
        </svg>
      </button>
    </div>

    <div class="body">
      <h3 class="name">{{ delegate.name }}</h3>
      <p v-if="delegate.job_title" class="role">{{ delegate.job_title }}</p>
      <p v-if="delegate.company" class="co">{{ delegate.company }}</p>
    </div>

    <!-- Reveal row: slides the card taller instead of covering the photo. -->
    <div class="reveal" @click.stop>
      <div class="reveal-inner">
        <EventNotePopover type="delegate" :id="delegate.id" block />
        <button type="button" class="act chat" :disabled="contacting" @click="contact">
          <svg viewBox="0 0 24 24">
            <path
              d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
          </svg>
          {{ contacting ? 'Opening…' : 'Chat' }}
        </button>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  --reveal-h: 56px;
  position: relative;
  background: #fff;
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid transparent;
  /* box-shadow: 0 1px 2px rgba(15, 23, 42, .05); */
  cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
  margin: 0;
}

.card:hover,
.card:focus-within,
.card.pinned {
  border-color: var(--brand-primary);
  box-shadow: 0 8px 20px rgba(15, 23, 42, .1);
}

.photo {
  position: relative;
  /* aspect-ratio: 11 / 10; */
  max-height: 180px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
}

.photo :deep(img),
.photo :deep(svg) {
  width: 100%;
  height: 100%;
  object-fit: cover;
  max-height: 180px;
}

.bm {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 2;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: #fff;
  color: var(--brand-primary);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(15, 23, 42, .18);
}

.bm svg {
  width: 19px;
  height: 19px;
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
  padding: 14px 16px;
}

.name {
  margin: 0;
  font-size: 1rem;
  line-height: 1.2;
  font-weight: 700;
  color: var(--brand-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.role {
  margin: 6px 0 0;
  color: #64676A;
  font-size: 14px;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.co {
  margin: 2px 0 0;
  color: #64676A;
  font-size: 12px;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* ── Reveal: Add Note / Chat, slides the card taller ── */
.reveal {
  height: 0;
  opacity: 0;
  overflow: hidden;
  transition: height .2s ease, opacity .15s ease;
}

.card:hover .reveal,
.card:focus-within .reveal,
.card.pinned .reveal {
  height: var(--reveal-h);
  opacity: 1;
}

.reveal-inner {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 0 16px 16px;
}

/* The note popover's own `block` styling already matches this look — just
   make it share the row equally with the Chat button. */
.reveal-inner :deep(.act) {
  flex: 1;
  min-width: 0;
}

.act.chat {
  flex: 1;
  min-width: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  border: 1px solid color-mix(in srgb, var(--brand-primary) 24%, #fff);
  border-radius: 8px;
  height: 40px;
  padding: 0 10px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: var(--brand-primary);
  font: inherit;
  font-size: .84rem;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.act.chat:hover {
  background: color-mix(in srgb, var(--brand-primary) 18%, #fff);
}

.act.chat:disabled {
  cursor: default;
}

.act.chat svg {
  width: 16px;
  height: 16px;
  flex: none;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}
</style>

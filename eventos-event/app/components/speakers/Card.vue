<script setup lang="ts">
import type { Speaker } from '~/stores/speakers'

const props = defineProps<{ speaker: Speaker }>()
const store = useSpeakersStore()
const bookmarks = useBookmarksStore()
const auth = useAuthStore()

const bookmarked = computed(() => bookmarks.isOn('speaker', props.speaker.id))
const connectState = computed(() => store.connected[props.speaker.id])
</script>

<template>
  <article class="card" @click="store.open(speaker)">
    <div class="thumb">
      <UserAvatar :src="speaker.image_url" :name="speaker.name" />
      <button class="bm" :class="{ on: bookmarked }" type="button" :title="bookmarked ? 'Saved' : 'Save'"
        @click.stop="bookmarks.toggle('speaker', speaker.id)">
        <svg viewBox="0 0 24 24">
          <path d="M6 3h12v18l-6-4-6 4z" />
        </svg>
      </button>
    </div>

    <div class="body">
      <h3 class="name">{{ speaker.name }}</h3>
      <p v-if="speaker.designation" class="role">{{ speaker.designation }}</p>
      <p v-if="speaker.company" class="company">{{ speaker.company }}</p>
    </div>

    <!-- Hover-revealed quick actions: the card's total height never changes —
         the thumbnail shrinks on hover to free up exactly the space this
         area slides into (bottom to top), instead of pushing the card taller. -->
    <div class="reveal">
      <div class="reveal-inner">
        <div v-if="auth.isAuthed" class="acts">
          <button class="act connect" :class="{ on: connectState === 'pending' }" type="button"
            :title="connectState === 'pending' ? 'Request sent' : 'Connect'" :disabled="connectState === 'pending'"
            @click.stop="store.connect(speaker)">
            <svg v-if="connectState === 'pending'" viewBox="0 0 24 24">
              <path d="M20 6L9 17l-5-5" />
            </svg>
            <svg v-else viewBox="0 0 24 24">
              <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM19 8v6M22 11h-6" />
            </svg>
          </button>
          <EventNotePopover type="speaker" :id="speaker.id" block class="note-fill" />
        </div>
        <!-- <button class="view" type="button" @click.stop="store.open(speaker)">View Profile</button> -->
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  display: flex;
  flex-direction: column;
  background: #fff;
  border-radius: 12px;
  cursor: pointer;
  border: 1px solid transparent;
  transition: all 500ms ease-in;
  border: 1px solid #E8E8EE;
  padding: 0
}

.card:hover,
.card:focus-within {
  box-shadow: 0 10px 26px rgba(15, 23, 42, .12);
  transform: translateY(-2px);
  border-color: color-mix(in srgb, var(--brand-primary) 45%, #fff);
}

.thumb {
  position: relative;
  width: 100%;
  aspect-ratio: 1 / 1;
  border-radius: 12px 12px 0px 0px;
  overflow: hidden;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  transition: aspect-ratio .22s ease;
}

.card:hover .thumb,
.card:focus-within .thumb {
  aspect-ratio: 2 / 1.6;
}

.thumb :deep(.ua) {
  border-radius: 0;
}

.bm {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  border: none;
  background: #fff;
  color: var(--brand-primary);
  box-shadow: 0 2px 6px rgba(15, 23, 42, .15);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.bm svg {
  width: 17px;
  height: 17px;
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
  text-align: left;
  min-width: 0;
  width: 100%;
  padding: 12px;

}

.name {
  margin: 0;
  font-size: 16px;
  line-height: 1.2;
  margin-bottom: 3px;
  font-weight: 600;
  color: #373F66;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.role {
  margin: 3px 0 0;
  color: #64676A;
  font-size: 14px;
  line-height: 1.2;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.company {
  margin: 2px 0 0;
  color: #64676A;
  font-size: 12px;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Collapsed by default; on hover the thumb shrinks by the same amount this
   area grows into, so the card's total height stays constant — the content
   slides up from the bottom rather than pushing the card taller. */
.reveal {
  max-height: 0;
  opacity: 0;
  overflow: hidden;
  transition: max-height .22s ease, opacity .18s ease;
}

.card:hover .reveal,
.card:focus-within .reveal {
  max-height: 68px;
  opacity: 1;
}

.reveal-inner {
  transform: translateY(14px);
  transition: transform .22s ease;
}

.card:hover .reveal-inner,
.card:focus-within .reveal-inner {
  transform: translateY(0);
}

@media (hover: none) {
  .reveal {
    max-height: 68px;
    opacity: 1;
  }

  .reveal-inner {
    transform: translateY(0);
  }
}

.acts {
  display: flex;
  gap: 8px;
  padding: 0 6px;
  margin-bottom: 10px;
}

.act {
  flex: none;
  width: 44px;
  height: 40px;
  border-radius: 10px;
  border: 1px solid transparent;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.act svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.act.connect {
  background: var(--brand-primary);
  color: #fff;
}

.act.connect.on {
  background: #16a34a;
}

.act.connect:disabled {
  cursor: default;
}

.note-fill {
  flex: 1;
}
</style>

<script setup lang="ts">
import type { ReceptionSpeaker } from '~/stores/reception'

const props = defineProps<{ speakers: ReceptionSpeaker[], limit?: number }>()

const bookmarks = useBookmarksStore()

const visible = computed(() => props.limit ? props.speakers.slice(0, props.limit) : props.speakers)

</script>

<template>
  <section class="speakers-featured">
    <header class="head">
      <h2>Featured Speakers ({{ speakers.length }})</h2>
    </header>

    <div class="grid">
      <article v-for="sp in visible" :key="sp.id" class="spk">
        <div class="photo">
          <UserAvatar :src="sp.image_url" :name="sp.name" />

          <button class="bookmark" :class="{ on: bookmarks.isOn('speaker', sp.id) }" type="button"
            :title="bookmarks.isOn('speaker', sp.id) ? 'Remove bookmark' : 'Bookmark'"
            @click="bookmarks.toggle('speaker', sp.id)">
            <svg viewBox="0 0 24 24">
              <path d="M6 3h12v18l-6-4-6 4z" />
            </svg>
          </button>
        </div>

        <div class="foot">
          <h3 class="name">{{ sp.name }}</h3>
          <p v-if="sp.designation" class="role">{{ sp.designation }}</p>
          <p v-if="sp.company" class="company">{{ sp.company }}</p>
        </div>
      </article>
    </div>

    <div class="viewall">
      <span class="line" />
      <NuxtLink to="/speakers" class="viewall-btn">View all speakers</NuxtLink>
      <span class="line" />
    </div>
  </section>
</template>

<style scoped>
.speakers-featured {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.head h2 {
  margin: 0;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.4;
  color: #4D5154;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 20px;
}

.spk {
  background: #fff;
  border: 1px solid #eef0f3;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.photo {
  position: relative;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo img {
  width: 100%;
  height: 100%;
  max-height: 210px;
  min-height: 210px;
  object-fit: cover;
  display: block;
}

.ph {
  font-size: 2.2rem;
  font-weight: 800;
  color: var(--brand-primary);
}

.bookmark {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  border: none;
  background: rgba(255, 255, 255, .92);
  color: var(--brand-primary);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 2px 6px rgba(15, 23, 42, .12);
}

.bookmark:hover {
  background: var(--brand-primary);
  color: #fff;
}

.bookmark.on {
  background: var(--brand-primary);
  color: #fff;
}

.bookmark svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.bookmark.on svg {
  fill: currentColor;
}

.foot {
  padding: 16px;
}

.name {
  margin: 0;
  font-size: 16px;;
  font-weight: 700;
  line-height: 1.4;
  color: #212529;
}

.role {
  margin: 4px 0 0;
  font-size: 14px;
  line-height: 1.4;
  color: #64676A;
}

.company {
  margin: 2px 0 0;
  font-size: 12px;
  line-height: 1.4;
  color: #64676A;
}

.viewall {
  display: flex;
  align-items: center;
  gap: 24px;
  margin-top: 8px;
}

.viewall .line {
  flex: 1;
  height: 1px;
  background: #D1D2DE;
}

.viewall-btn {
  flex: 0 0 auto;
  padding: 8px 16px;
  border-radius: 8px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: var(--brand-primary);
  font-weight: 700;
  font-size: .88rem;
  text-decoration: none;
}

.viewall-btn:hover {
  background: color-mix(in srgb, var(--brand-primary) 18%, #fff);
}
</style>

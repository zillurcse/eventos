<script setup lang="ts">
/**
 * A horizontal scroller with prev/next arrows + page dots. Slot in the cards;
 * arrows scroll by a page width, dots reflect the current scroll page.
 */
const track = ref<HTMLElement | null>(null)
const page = ref(0)
const pages = ref(1)

function measure() {
  const el = track.value
  if (!el) return
  const per = Math.max(el.clientWidth, 1)
  pages.value = Math.max(1, Math.round(el.scrollWidth / per))
  page.value = Math.round(el.scrollLeft / per)
}

function scrollByPage(dir: number) {
  const el = track.value
  if (!el) return
  el.scrollBy({ left: dir * el.clientWidth * 0.9, behavior: 'smooth' })
}

onMounted(() => {
  measure()
  window.addEventListener('resize', measure)
})
onBeforeUnmount(() => window.removeEventListener('resize', measure))
</script>

<template>
  <div class="carousel">
    <button class="arrow left" type="button" aria-label="Previous" @click="scrollByPage(-1)">
      <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
    </button>

    <div ref="track" class="track" @scroll="measure">
      <slot />
    </div>

    <button class="arrow right" type="button" aria-label="Next" @click="scrollByPage(1)">
      <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
    </button>

    <div v-if="pages > 1" class="dots">
      <span v-for="i in pages" :key="i" class="dot" :class="{ on: (i - 1) === page }" />
    </div>
  </div>
</template>

<style scoped>
.carousel { position: relative; }
.track { display: flex; gap: 14px; overflow-x: auto; scroll-snap-type: x proximity; padding: 2px; scrollbar-width: none; }
.track::-webkit-scrollbar { display: none; }
.track :deep(> *) { scroll-snap-align: start; }

.arrow {
  position: absolute; top: 50%; transform: translateY(-60%); z-index: 2;
  width: 30px; height: 30px; border-radius: 50%; border: 1px solid #e2e8f0;
  background: #fff; box-shadow: 0 2px 8px rgba(15,23,42,.12); cursor: pointer;
  display: flex; align-items: center; justify-content: center; color: var(--brand-primary);
}
.arrow:hover { background: var(--brand-primary); color: #fff; }
.arrow svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.arrow.left { left: -10px; }
.arrow.right { right: -10px; }

.dots { display: flex; justify-content: center; gap: 6px; margin-top: 12px; }
.dot { width: 7px; height: 7px; border-radius: 50%; background: #cbd5e1; }
.dot.on { background: var(--brand-primary); }
</style>

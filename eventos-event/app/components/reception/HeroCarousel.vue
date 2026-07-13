<!-- <script setup lang="ts">
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Autoplay } from 'swiper/modules'
import type { Swiper as SwiperInstance } from 'swiper'
import 'swiper/css'

const props = defineProps<{ banners: string[] }>()

const count = computed(() => props.banners.length)
const loop = computed(() => count.value > 1)

/** Swiper's loop math needs real slides >= slidesPerView(1.3) + slidesPerGroup(1) + 1
 *  (centeredSlides) = 3.3 — pad by repeating so fewer than 4 banners still loop cleanly
 *  on both sides instead of leaving one edge blank on first paint. */
const loopSlides = computed(() => {
  const b = props.banners
  const MIN = 6
  if (b.length === 0 || b.length >= MIN) return b
  const padded: string[] = []
  while (padded.length < MIN) padded.push(...b)
  return padded
})

const modules = [Autoplay]

const swiperRef = ref<SwiperInstance | null>(null)
const onSwiper = (s: SwiperInstance) => { swiperRef.value = s }
const prev = () => swiperRef.value?.slidePrev()
const next = () => swiperRef.value?.slideNext()
</script>

<template>
  <div class="hero">
    <Swiper
      :modules="modules"
      :slides-per-view="1.3"
      :centered-slides="true"
      :space-between="10"
      :loop="loop"
      :speed="600"
      :autoplay="count > 1 ? { delay: 6000, disableOnInteraction: false, pauseOnMouseEnter: true } : false"
      class="swiper"
      @swiper="onSwiper"
    >
      <SwiperSlide v-for="(b, i) in loopSlides" :key="i" class="slide">
        <img :src="b" alt="" />
      </SwiperSlide>
    </Swiper>

    <div class="fade left" />
    <div class="fade right" />

    <template v-if="count > 1">
      <button class="hero-nav left" type="button" aria-label="Previous slide" @click="prev">
        <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <button class="hero-nav right" type="button" aria-label="Next slide" @click="next">
        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </button>
    </template>
  </div>
</template>

<style scoped>
.hero { position: relative;border-radius: 8px; overflow: hidden;max-height:350px;aspect-ratio: 16/9; }
.swiper { width: 100%; height: 100%; }

.slide { background: transparent; }
.slide img { width: 100%; height: 100%; object-fit: cover; display: block;border-radius: 8px; }

/* Soft vignette so the peeked neighbour slides blend into the page canvas
   instead of showing a hard slide boundary next to the active slide. */
.fade { position: absolute; top: 0; bottom: 0; width: 16%; z-index: 1; pointer-events: none; }
.fade.left { left: 0; background: linear-gradient(to right, #e9ebee, rgba(233,235,238,.35) 60%, transparent); }
.fade.right { right: 0; background: linear-gradient(to left, #e9ebee, rgba(233,235,238,.35) 60%, transparent); }

.hero-nav {
  position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
  width: 34px; height: 34px; border-radius: 50%; border: none; cursor: pointer;
  background: rgba(255,255,255,.9); color: #334155; display: flex; align-items: center; justify-content: center;
  box-shadow: 0 2px 8px rgba(15,23,42,.12);
}
.hero-nav:hover { background: #fff; }
.hero-nav svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.hero-nav.left { left: 8%; }
.hero-nav.right { right: 8%; }

@media (max-width: 720px) {
  .hero-nav.left { left: 10px; }
  .hero-nav.right { right: 10px; }
}
</style> -->

<script setup lang="ts">
import { computed, ref } from 'vue'
import { Swiper, SwiperSlide } from 'swiper/vue'
import { Navigation, Autoplay } from 'swiper/modules'
import type { Swiper as SwiperInstance } from 'swiper'

import 'swiper/css'

const props = defineProps<{
  banners: string[]
}>()

const count = computed(() => props.banners.length)
const loop = computed(() => count.value > 1)

const loopSlides = computed(() => {
  const MIN = 6

  if (props.banners.length >= MIN) return props.banners

  const arr: string[] = []

  while (arr.length < MIN) {
    arr.push(...props.banners)
  }

  return arr
})

const swiper = ref<SwiperInstance>()

const onSwiper = (s: SwiperInstance) => {
  swiper.value = s
}

const prev = () => swiper.value?.slidePrev()
const next = () => swiper.value?.slideNext()

const modules = [Navigation, Autoplay]
</script>

<template>
  <div class="hero-slider">
    <Swiper
      class="hero-swiper"
      :modules="modules"
      :slides-per-view="1.6"
      :centered-slides="true"
      :space-between="12"
      :loop="loop"
      :speed="700"
      :autoplay="
        count > 1
          ? {
              delay: 5000,
              disableOnInteraction: false,
              pauseOnMouseEnter: true,
            }
          : false
      "
      @swiper="onSwiper"
    >
      <SwiperSlide
        v-for="(banner, index) in loopSlides"
        :key="index"
      >
        <img
          :src="banner"
          alt=""
        />
      </SwiperSlide>
    </Swiper>

    <button
      v-if="count > 1"
      class="nav prev"
      @click="prev"
    >
      ←
    </button>

    <button
      v-if="count > 1"
      class="nav next"
      @click="next"
    >
      →
    </button>
  </div>
</template>

<style scoped>
.hero-slider {
  position: relative;
  padding: 0 0px;
}

/* IMPORTANT */


:deep(.swiper-wrapper) {
  align-items: center;
}

:deep(.swiper-slide) {
  transition: all .45s ease;
  transform: scale(.88);
  opacity: .35;
}

:deep(.swiper-slide-active) {
  transform: scale(1);
  opacity: 1;
}

:deep(.swiper-slide-prev),
:deep(.swiper-slide-next) {
  opacity: .85;
  transform: scale(1);
}

:deep(.swiper-slide img) {
  width: 100%;
  height: 240px;
  display: block;
  object-fit: cover;
  border-radius: 18px;
  overflow: hidden;
}

/* fade side slides */

:deep(.swiper-slide:not(.swiper-slide-active))::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 18px;
  background: rgba(255,255,255,.45);
  pointer-events: none;
}

/* navigation */

.nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 56px;
  height: 56px;
  border: none;
  border-radius: 16px;
  background: white;
  cursor: pointer;
  font-size: 28px;
  font-weight: 600;
  color: #6a5cff;
  box-shadow: 0 10px 30px rgba(0,0,0,.12);
  z-index: 50;
  transition: .25s;
}

.nav:hover {
  transform: translateY(-50%) scale(1.05);
}

.prev {
  left: 14%;
}

.next {
  right: 14%;
}

@media (max-width:1024px){

  .hero-slider{
    padding:0 0px;
  }

  :deep(.swiper-slide img){
      height:220px;
  }

  .prev{
      left:20px;
  }

  .next{
      right:20px;
  }

}
</style>
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
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
      <path d="M10.995 7.00579L3.40501 7.0058L6.70501 10.2958C6.89332 10.4841 6.9991 10.7395 6.9991 11.0058C6.9991 11.2721 6.89332 11.5275 6.70501 11.7158C6.51671 11.9041 6.26132 12.0099 5.99501 12.0099C5.72871 12.0099 5.47332 11.9041 5.28501 11.7158L0.285013 6.7158C0.193972 6.62069 0.122607 6.50855 0.075013 6.3858C-0.025005 6.14233 -0.025005 5.86926 0.075013 5.6258C0.122607 5.50304 0.193972 5.3909 0.285013 5.2958L5.28501 0.295797C5.37798 0.202069 5.48858 0.127673 5.61044 0.0769038C5.73229 0.0261349 5.863 -2.41991e-06 5.99501 -2.43145e-06C6.12702 -2.44299e-06 6.25773 0.0261349 6.37959 0.0769037C6.50145 0.127673 6.61205 0.202069 6.70501 0.295797C6.79874 0.38876 6.87314 0.499359 6.9239 0.621219C6.97467 0.743079 7.00081 0.873784 7.00081 1.0058C7.00081 1.13781 6.97467 1.26851 6.9239 1.39037C6.87314 1.51223 6.79874 1.62283 6.70501 1.71579L3.40501 5.0058L10.995 5.00579C11.2602 5.00579 11.5146 5.11115 11.7021 5.29869C11.8897 5.48622 11.995 5.74058 11.995 6.00579C11.995 6.27101 11.8897 6.52537 11.7021 6.7129C11.5146 6.90044 11.2602 7.00579 10.995 7.00579Z" fill="var(--brand-primary)"/>
    </svg>
    </button>

    <button
      v-if="count > 1"
      class="nav next"
      @click="next"
    >
    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12" fill="none">
      <path d="M11.92 5.62409C11.8724 5.50134 11.801 5.3892 11.71 5.29409L6.71 0.294092C6.61676 0.200853 6.50607 0.126893 6.38425 0.0764322C6.26243 0.0259719 6.13186 0 6 0C5.7337 0 5.4783 0.105788 5.29 0.294092C5.19676 0.38733 5.1228 0.498021 5.07234 0.619843C5.02188 0.741665 4.99591 0.872233 4.99591 1.00409C4.99591 1.27039 5.1017 1.52579 5.29 1.71409L8.59 5.00409H1C0.734784 5.00409 0.48043 5.10945 0.292893 5.29699C0.105357 5.48452 0 5.73888 0 6.00409C0 6.26931 0.105357 6.52366 0.292893 6.7112C0.48043 6.89873 0.734784 7.00409 1 7.00409H8.59L5.29 10.2941C5.19627 10.3871 5.12188 10.4977 5.07111 10.6195C5.02034 10.7414 4.9942 10.8721 4.9942 11.0041C4.9942 11.1361 5.02034 11.2668 5.07111 11.3887C5.12188 11.5105 5.19627 11.6211 5.29 11.7141C5.38296 11.8078 5.49356 11.8822 5.61542 11.933C5.73728 11.9838 5.86799 12.0099 6 12.0099C6.13201 12.0099 6.26272 11.9838 6.38458 11.933C6.50644 11.8822 6.61704 11.8078 6.71 11.7141L11.71 6.71409C11.801 6.61899 11.8724 6.50684 11.92 6.38409C12.02 6.14063 12.02 5.86755 11.92 5.62409Z" fill="var(--brand-primary)"/>
    </svg>
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
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: #F0EEFD;
  cursor: pointer;
  font-size: 28px;
  font-weight: 600;
  color: var(--brand-primary);
  z-index: 50;
  transition: .25s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav:hover {
  transform: translateY(-50%) scale(1.05);
}

.prev {
  left: 17%;
}

.next {
  right: 17%;
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
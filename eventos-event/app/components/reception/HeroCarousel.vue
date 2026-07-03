<script setup lang="ts">
const props = defineProps<{ banners: string[] }>()

const index = ref(0)
let timer: ReturnType<typeof setInterval> | null = null

const count = computed(() => props.banners.length)

function go(i: number) {
  if (count.value === 0) return
  index.value = (i + count.value) % count.value
}

function restart() {
  if (timer) clearInterval(timer)
  if (count.value > 1) timer = setInterval(() => go(index.value + 1), 6000)
}

onMounted(restart)
onBeforeUnmount(() => { if (timer) clearInterval(timer) })
</script>

<template>
  <div class="hero">
    <div class="viewport">
      <img
        v-for="(b, i) in banners"
        :key="i"
        :src="b"
        class="slide"
        :class="{ on: i === index }"
        alt=""
      />
    </div>

    <template v-if="count > 1">
      <button class="nav left" type="button" aria-label="Previous" @click="go(index - 1); restart()">
        <svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6" /></svg>
      </button>
      <button class="nav right" type="button" aria-label="Next" @click="go(index + 1); restart()">
        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </button>
    </template>
  </div>
</template>

<style scoped>
.hero { position: relative; border-radius: 16px; overflow: hidden; background: var(--brand-primary); aspect-ratio: 1000 / 300; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.viewport { position: absolute; inset: 0; }
.slide { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity .6s ease; }
.slide.on { opacity: 1; }

.nav {
  position: absolute; top: 50%; transform: translateY(-50%); z-index: 2;
  width: 34px; height: 34px; border-radius: 50%; border: none; cursor: pointer;
  background: rgba(255,255,255,.85); color: #334155; display: flex; align-items: center; justify-content: center;
}
.nav:hover { background: #fff; }
.nav svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
.nav.left { left: 12px; }
.nav.right { right: 12px; }
</style>

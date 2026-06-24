<script setup lang="ts">
const props = defineProps<{ url?: string | null, seed?: string, label?: string }>()

const palettes = [
  ['#6366f1', '#8b5cf6'], ['#ef4444', '#f97316'], ['#0ea5e9', '#22d3ee'],
  ['#10b981', '#34d399'], ['#f59e0b', '#f43f5e'], ['#8b5cf6', '#ec4899'],
  ['#0f766e', '#14b8a6'], ['#1e293b', '#475569'],
]
function hash(s: string) { let h = 0; for (let i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h }
const grad = computed(() => {
  const p = palettes[hash(props.seed || props.label || 'x') % palettes.length]
  return `linear-gradient(135deg, ${p[0]}, ${p[1]})`
})
const short = computed(() => (props.label || '').split(/\s+/).map(w => w[0]).slice(0, 3).join('').toUpperCase())
</script>

<template>
  <div class="cover" :style="url ? undefined : `background:${grad}`">
    <img v-if="url" :src="url" alt="">
    <span v-else class="cover-label">{{ short || label }}</span>
    <slot />
  </div>
</template>

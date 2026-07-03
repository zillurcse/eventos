<script setup lang="ts">
defineProps<{
  title: string
  featured?: boolean
  viewAll?: boolean
}>()
</script>

<template>
  <section class="sec">
    <header class="sec-head">
      <div class="sec-title">
        <h2>{{ title }}</h2>
        <span v-if="featured" class="pill">Featured</span>
      </div>
      <a v-if="viewAll" href="#" class="viewall" @click.prevent>
        View all
        <svg viewBox="0 0 24 24"><path d="M9 6l6 6-6 6" /></svg>
      </a>
    </header>
    <slot />
  </section>
</template>

<style scoped>
.sec { background: #fff; border-radius: 16px; padding: 18px 18px 20px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.sec-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.sec-title { display: flex; align-items: center; gap: 10px; }
.sec-title h2 { margin: 0; font-size: .95rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #334155; }
.pill { font-size: .62rem; font-weight: 700; color: var(--brand-primary); background: color-mix(in srgb, var(--brand-primary) 12%, #fff); border: 1px solid color-mix(in srgb, var(--brand-primary) 30%, #fff); padding: 3px 9px; border-radius: 999px; text-transform: none; letter-spacing: 0; }
.viewall { display: inline-flex; align-items: center; gap: 3px; font-size: .78rem; font-weight: 600; color: var(--brand-primary); }
.viewall svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
</style>

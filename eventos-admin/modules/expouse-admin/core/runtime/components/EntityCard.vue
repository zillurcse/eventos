<script setup lang="ts">
defineProps<{ title: string, status?: string, coverUrl?: string | null, seed?: string, to?: string }>()
const open = ref(false)
const NuxtLink = resolveComponent('NuxtLink')
</script>

<template>
  <div class="entity-card">
    <component :is="to ? NuxtLink : 'div'" :to="to" class="block text-[inherit]">
      <AppCover :url="coverUrl" :seed="seed || title" :label="title">
        <span v-if="status" class="status-pill" :class="status"><span class="dot" />{{ status }}</span>
      </AppCover>
    </component>
    <div class="body">
      <div class="min-w-0">
        <component :is="to ? NuxtLink : 'div'" :to="to" class="title text-[inherit] block">{{ title }}</component>
        <div class="meta"><slot name="meta" /></div>
      </div>
      <div v-if="$slots.menu" class="menu">
        <button class="menu-btn" aria-label="Actions" @click="open = !open">⋮</button>
        <div v-if="open" class="fixed inset-0 z-30" @click="open = false" />
        <div v-if="open" class="menu-pop" @click="open = false">
          <slot name="menu" />
        </div>
      </div>
    </div>
  </div>
</template>

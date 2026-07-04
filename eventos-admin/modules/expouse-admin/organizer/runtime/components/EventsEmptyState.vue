<script setup lang="ts">
withDefaults(defineProps<{ icon?: string, title?: string, description: string, ctaLabel?: string, compact?: boolean }>(), {
  icon: 'calendar',
})
defineEmits<{ (e: 'cta'): void }>()
</script>

<template>
  <div class="empty-state" :class="{ compact }">
    <div class="empty-state__icon"><Icon :name="icon" /></div>
    <p v-if="title" class="empty-state__title">{{ title }}</p>
    <p class="muted">{{ description }}</p>
    <button v-if="ctaLabel" class="btn mt-3" @click="$emit('cta')">{{ ctaLabel }}</button>
  </div>
</template>

<style scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  gap: 6px;
  padding: 56px 24px;
  background: #fff;
  border: 1px dashed var(--line);
  border-radius: 16px;
  margin-bottom: 30px;
}
.empty-state.compact {
  padding: 32px 20px;
  margin-bottom: 0;
}
.empty-state__icon {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--brand-soft);
  color: var(--brand);
  display: grid;
  place-items: center;
  margin-bottom: 6px;
}
.empty-state__icon :deep(svg) {
  width: 20px;
  height: 20px;
}
.empty-state.compact .empty-state__icon {
  width: 36px;
  height: 36px;
}
.empty-state.compact .empty-state__icon :deep(svg) {
  width: 16px;
  height: 16px;
}
.empty-state__title {
  font-weight: 700;
  color: var(--ink);
  font-size: 0.98rem;
}
</style>

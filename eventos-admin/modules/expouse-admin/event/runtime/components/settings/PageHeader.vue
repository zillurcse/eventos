<script setup lang="ts">
defineProps<{ title: string, subtitle?: string, saving?: boolean, saved?: boolean, saveLabel?: string }>()
defineEmits<{ (e: 'save'): void }>()
</script>

<template>
  <div class="flex items-center justify-between gap-4 flex-wrap mb-6">
    <div>
      <h1 class="text-[1.35rem] font-bold text-ink mb-0.5">{{ title }}</h1>
      <p v-if="subtitle" class="text-muted text-[.88rem]">{{ subtitle }}</p>
    </div>
    <button class="btn" :disabled="saving" @click="$emit('save')">
      <svg v-if="saving" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
      </svg>
      <svg v-else-if="saved" width="14" height="14" viewBox="0 0 24 24" fill="none">
        <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      {{ saving ? 'Saving…' : saved ? 'Saved' : (saveLabel || 'Save changes') }}
    </button>
  </div>
</template>

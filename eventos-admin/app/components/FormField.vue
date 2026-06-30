<script setup lang="ts">
defineProps<{
  label?:    string
  required?: boolean
  error?:    string
  hint?:     string
}>()
</script>

<template>
  <div>
    <label v-if="label" class="block mb-1.5">
      {{ label }}<span v-if="required" class="text-[#dc2626] ml-0.5">*</span>
    </label>
    <slot />
    <p v-if="hint && !error" class="text-[.8rem] text-muted mt-1 mb-0">{{ hint }}</p>
    <p v-if="error" class="error mt-1 mb-0">{{ error }}</p>
  </div>
</template>

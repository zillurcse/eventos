<script setup lang="ts">
defineProps<{ title?: string }>()
const emit = defineEmits<{ (e: 'close'): void }>()
</script>

<template>
  <div class="fixed inset-0 bg-[rgba(17,20,36,.35)] z-[200] flex justify-end" @click.self="emit('close')">
    <div class="w-[480px] max-w-[94vw] h-full bg-white shadow-[-8px_0_30px_rgba(0,0,0,.12)] flex flex-col animate-[drawer-in_.22s_ease]">
      <div class="flex items-center justify-between px-[22px] py-4 border-b border-line bg-[#f7f8fa] shrink-0">
        <h2 class="m-0 text-[1.1rem]">{{ title }}</h2>
        <button class="w-[34px] h-[34px] rounded-full border-[1.5px] border-[#d7dae1] bg-white cursor-pointer text-[#5f6b7a] grid place-items-center hover:bg-[#f0f0f5]" aria-label="Close" @click="emit('close')">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
        </button>
      </div>
      <div class="p-[22px] overflow-y-auto flex-1"><slot /></div>
    </div>
  </div>
</template>

<style>
@keyframes drawer-in { from { transform: translateX(100%); } to { transform: translateX(0); } }
</style>

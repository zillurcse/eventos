<script setup lang="ts">
const { entitlements, subSaving, subError, savePermissions } = useExhibitorContext()
</script>

<template>
  <div>
    <p class="muted text-[.86rem] mt-0 mb-3">Toggle which Showcase features this exhibitor can use, and set per-feature limits.</p>
    <div class="flex flex-col gap-2">
      <div
        v-for="f in entitlements" :key="f.key"
        class="flex items-center gap-3 px-4 py-2.5 border border-line rounded-xl bg-[#fafbfc]"
        :class="{ 'bg-brand-soft border-brand/20': f.enabled }"
      >
        <input v-model="f.enabled" type="checkbox" class="w-4.5 h-4.5 m-0 rounded shrink-0 cursor-pointer accent-brand">
        <span class="flex-1 text-[.93rem] font-medium text-ink select-none">{{ featureLabel(f.key) }}</span>
        <div class="flex items-center shrink-0 border border-[#d7dae1] rounded-xl overflow-hidden bg-white">
          <button class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer" @click="f.limit = Math.max(0, f.limit - 1)">−</button>
          <span class="w-8 h-9 flex items-center justify-center text-[.91rem] font-semibold border-x border-[#d7dae1] select-none">{{ f.limit }}</span>
          <button class="w-9 h-9 flex items-center justify-center text-[1.1rem] text-muted border-0 bg-transparent cursor-pointer" @click="f.limit++">+</button>
        </div>
      </div>
    </div>
    <p v-if="subError" class="error mt-3">{{ subError }}</p>
    <div class="flex pt-4 mt-2">
      <button class="btn flex-1 py-3 tracking-widest" :disabled="subSaving" @click="savePermissions">
        {{ subSaving ? 'SAVING…' : 'SAVE PERMISSIONS' }}
      </button>
    </div>
  </div>
</template>

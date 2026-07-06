<script setup lang="ts">
const auth = useAuthStore()
const open = ref(false)
const initials = computed(() => (auth.user?.name || auth.user?.email || 'EE').split(/\s+/).map(s => s[0]).slice(0, 2).join('').toUpperCase())
</script>

<template>
  <div class="user-chip" @click="open = !open">
    <span class="avatar">{{ initials }}</span>
    <span class="font-semibold text-[.9rem]">{{ auth.user?.name }}</span>
    <div v-if="open" class="menu-pop" @click.stop>
      <div class="muted px-3 py-2 text-[.8rem]">{{ auth.user?.email }}</div>
      <button @click="auth.logout()"><AppIcon name="logout" class="w-[15px] h-[15px] align-[-2px] mr-1.5" />Sign out</button>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const route = useRoute()

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
})

interface NavItem { to: string, label: string, icon: string }

const nav = computed<NavItem[]>(() => {
  if (!auth.isAuthed) return []
  if (auth.isPlatform) return [
    { to: '/', label: 'Dashboard', icon: 'pie' },
    { to: '/organizations', label: 'Organizations', icon: 'grid' },
    { to: '/staff', label: 'Staff', icon: 'users' },
    { to: '/organizers', label: 'Organizers', icon: 'briefcase' },
    { to: '/exhibitors', label: 'Exhibitors', icon: 'store' },
    { to: '/plans', label: 'Plans', icon: 'layers' },
  ]
  if (auth.isOrganizer) return [
    { to: '/org', label: 'Overview', icon: 'grid' },
    { to: '/org/events', label: 'Events', icon: 'calendar' },
    { to: '/org/team', label: 'Team', icon: 'users' },
  ]
  if (auth.isExhibitor) return [
    { to: '/exhibitor', label: 'My Booth', icon: 'store' },
    { to: '/exhibitor/products', label: 'Products', icon: 'box' },
    { to: '/exhibitor/members', label: 'Team', icon: 'users' },
  ]
  return []
})

function isActive(to: string) {
  return to === '/org/events' ? route.path.startsWith('/org/events') : route.path === to
}
const title = computed(() => (route.meta.title as string) || auth.orgName || auth.primaryExhibitor?.name || 'Welcome')
const subtitle = computed(() => (route.meta.subtitle as string) || 'Welcome to your dashboard')
</script>

<template>
  <div class="app-shell">
    <aside class="sidebar">
      <NuxtLink :to="auth.home" class="brand">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><rect x="2" y="9" width="3" height="13" rx="1"/><rect x="7" y="4" width="3" height="18" rx="1"/><rect x="12" y="11" width="3" height="11" rx="1"/><rect x="17" y="6" width="3" height="16" rx="1"/></svg>
        EventOS
      </NuxtLink>
      <nav class="nav-group">
        <NuxtLink v-for="item in nav" :key="item.to" :to="item.to" class="nav-item" :class="{ active: isActive(item.to) }">
          <Icon :name="item.icon" />
          <span>{{ item.label }}</span>
        </NuxtLink>
      </nav>
      <div class="spacer" />
    </aside>

    <div class="main">
      <header class="topbar">
        <div>
          <div class="tt">{{ title }}</div>
          <div class="ts">{{ subtitle }}</div>
        </div>
        <div class="grow" />
        <UserChip />
      </header>
      <main class="content">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const route = useRoute()

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
})

interface NavItem { to?: string, label: string, icon?: string, children?: NavItem[], feature?: string }

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
  if (auth.isExhibitor) {
    const items: NavItem[] = [
      { to: '/exhibitor', label: 'Company Profile', icon: 'store' },
      { to: '/exhibitor/inbox', label: 'Inbox', icon: 'mail' },
      { label: 'Leads', icon: 'target', children: [
        { to: '/exhibitor/leads', label: 'All Leads', feature: 'all_leads' },
        { to: '/exhibitor/leads/team-connections', label: 'Team Connections', feature: 'team_connections' },
        { to: '/exhibitor/leads/recommended', label: 'Recommended Leads', feature: 'recommended_leads' },
        { to: '/exhibitor/leads/qualification', label: 'Lead Qualification', feature: 'lead_qualification' },
        { to: '/exhibitor/leads/analytics', label: 'Leads Analytics', feature: 'lead_analytics' },
        { to: '/exhibitor/leads/export', label: 'Lead Export', feature: 'lead_export' },
      ] },
      { to: '/exhibitor/products', label: 'Products', icon: 'box', feature: 'products' },
      { to: '/exhibitor/documents', label: 'Documents', icon: 'clipboard', feature: 'documents' },
      { to: '/exhibitor/projects', label: 'Projects', icon: 'layers', feature: 'projects' },
      { to: '/exhibitor/members', label: 'Team Members', icon: 'users', feature: 'teams' },
      { to: '/exhibitor/request-service', label: 'Request Service', icon: 'briefcase' },
    ]
    // Apply the exhibitor's entitlements: drop gated items, and drop a group
    // once all its children are gated out.
    const allowed = (it: NavItem) => !it.feature || auth.hasFeature(it.feature)
    return items
      .map(it => it.children ? { ...it, children: it.children.filter(allowed) } : it)
      .filter(it => it.children ? it.children.length > 0 : allowed(it))
  }
  return []
})

function isActive(to: string) {
  return to === '/org/events' ? route.path.startsWith('/org/events') : route.path === to
}

// Collapsible groups (e.g. exhibitor Leads). Open a group when it holds the
// active route; the user can also toggle it manually.
const openGroups = ref<Set<string>>(new Set())
function groupHoldsRoute(item: NavItem) {
  return !!item.children?.some(c => c.to && (route.path === c.to || route.path.startsWith(c.to + '/')))
}
function isGroupOpen(item: NavItem) {
  return openGroups.value.has(item.label) || groupHoldsRoute(item)
}
function toggleGroup(label: string) {
  const s = new Set(openGroups.value)
  s.has(label) ? s.delete(label) : s.add(label)
  openGroups.value = s
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
        <template v-for="item in nav" :key="item.label">
          <!-- Collapsible group -->
          <template v-if="item.children">
            <button type="button" class="nav-item w-full text-left" :class="{ active: groupHoldsRoute(item) }" @click="toggleGroup(item.label)">
              <AppIcon v-if="item.icon" :name="item.icon" />
              <span>{{ item.label }}</span>
              <span class="grow" />
              <AppIcon name="chevron-down" class="chev" :class="{ open: isGroupOpen(item) }" />
            </button>
            <NuxtLink
              v-for="child in (isGroupOpen(item) ? item.children : [])"
              :key="child.to"
              :to="child.to!"
              class="nav-item nav-sub"
              :class="{ active: isActive(child.to!) }"
            >
              <span>{{ child.label }}</span>
            </NuxtLink>
          </template>

          <!-- Leaf item -->
          <NuxtLink v-else :to="item.to!" class="nav-item" :class="{ active: isActive(item.to!) }">
            <AppIcon v-if="item.icon" :name="item.icon" />
            <span>{{ item.label }}</span>
          </NuxtLink>
        </template>
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

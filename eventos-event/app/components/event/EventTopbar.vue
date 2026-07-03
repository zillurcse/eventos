<script setup lang="ts">
const auth = useAuthStore()
const site = useSiteStore()

const menuOpen = ref(false)

const initials = computed(() => {
  const n = (auth.user?.name || site.name || 'U').trim()
  const parts = n.split(/\s+/)
  return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || 'U'
})

// Static utility icons for now (notifications/inbox/alerts/chat are follow-ups).
const utilities = [
  { key: 'bookmarks', label: 'Saved', badge: null as number | null },
  { key: 'inbox', label: 'Inbox', badge: null },
  { key: 'alerts', label: 'Notifications', badge: null },
  { key: 'chat', label: 'Chat', badge: null },
]

function closeOnOutside(e: MouseEvent) {
  if (!(e.target as HTMLElement)?.closest?.('.user')) menuOpen.value = false
}
onMounted(() => document.addEventListener('click', closeOnOutside))
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutside))
</script>

<template>
  <header class="topbar">
    <div class="inner">
      <NuxtLink to="/reception" class="logo">
        <img v-if="site.logoUrl" :src="site.logoUrl" :alt="site.name" />
        <span v-else class="logo-badge">{{ (site.name || 'EV').slice(0, 3).toUpperCase() }}</span>
        <span class="logo-name">{{ site.name }}</span>
      </NuxtLink>

      <div class="spacer" />

      <nav class="utils" aria-label="Quick actions">
        <button v-for="u in utilities" :key="u.key" class="util" type="button" :title="u.label" :aria-label="u.label">
          <svg v-if="u.key === 'bookmarks'" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
          <svg v-else-if="u.key === 'inbox'" viewBox="0 0 24 24"><path d="M3 13h4l2 3h6l2-3h4M3 13l3-8h12l3 8v6H3z" /></svg>
          <svg v-else-if="u.key === 'alerts'" viewBox="0 0 24 24"><path d="M6 16V10a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" /></svg>
          <svg v-else viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z" /></svg>
          <span v-if="u.badge" class="dot">{{ u.badge }}</span>
        </button>
      </nav>

      <div class="sep" />

      <div class="user">
        <button class="user-btn" type="button" @click.stop="menuOpen = !menuOpen">
          <span class="name">{{ auth.user?.name || 'Guest' }}</span>
          <span class="avatar">{{ initials }}</span>
          <svg class="caret" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6" /></svg>
        </button>
        <div v-if="menuOpen" class="menu">
          <div class="menu-head">
            <strong>{{ auth.user?.name }}</strong>
            <small>{{ auth.user?.email }}</small>
          </div>
          <a href="#" class="menu-item" @click.prevent="auth.logout()">Logout</a>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar { background: #fff; border-bottom: 1px solid #e6e8ec; position: sticky; top: 0; z-index: 40; }
.inner { display: flex; align-items: center; gap: 14px; max-width: 1180px; margin: 0 auto; padding: 10px 18px; }
.spacer { flex: 1; }

.logo { display: flex; align-items: center; gap: 10px; color: var(--brand-primary); }
.logo img { max-height: 34px; max-width: 150px; object-fit: contain; }
.logo-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 30px; border-radius: 6px; background: var(--brand-primary); color: #fff; font-weight: 800; font-size: .72rem; }
.logo-name { font-weight: 700; color: #334155; font-size: .98rem; }

.utils { display: flex; align-items: center; gap: 4px; }
.util { position: relative; background: none; border: none; padding: 7px; border-radius: 8px; cursor: pointer; color: var(--brand-primary); line-height: 0; }
.util:hover { background: #f1f2f6; }
.util svg { width: 21px; height: 21px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.dot { position: absolute; top: 1px; right: 1px; min-width: 15px; height: 15px; padding: 0 3px; border-radius: 999px; background: #ef4444; color: #fff; font-size: .6rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }

.sep { width: 1px; height: 26px; background: #e6e8ec; }

.user { position: relative; }
.user-btn { display: flex; align-items: center; gap: 8px; background: none; border: none; cursor: pointer; padding: 4px; font: inherit; }
.name { font-size: .82rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .3px; }
.avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .8rem; }
.caret { width: 16px; height: 16px; fill: none; stroke: #94a3b8; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }

.menu { position: absolute; right: 0; top: calc(100% + 8px); background: #fff; border: 1px solid #e6e8ec; border-radius: 12px; box-shadow: 0 12px 30px rgba(15,23,42,.12); min-width: 220px; overflow: hidden; }
.menu-head { padding: 12px 14px; border-bottom: 1px solid #f1f2f6; display: flex; flex-direction: column; gap: 2px; }
.menu-head small { color: #94a3b8; }
.menu-item { display: block; padding: 11px 14px; color: #334155; font-weight: 600; }
.menu-item:hover { background: #f7f8fa; color: var(--brand-primary); }

@media (max-width: 720px) {
  .logo-name { display: none; }
  .name { display: none; }
}
</style>

<script setup lang="ts">
// Single-row event header: logo + title, section tabs, and utility/account
// icons all in one bar (replaces the old separate EventTopbar + EventNav).
interface Tab { key: string, label: string, to?: string, icon: string }

const tabs: Tab[] = [
  { key: 'reception', label: 'Reception', to: '/reception', icon: 'M4 20v-8l8-6 8 6v8h-6v-6h-4v6z' },
  { key: 'feed', label: 'Event Feed', to: '/feed', icon: 'M4 5h16M4 12h16M4 19h10' },
  { key: 'sessions', label: 'Sessions', to: '/sessions', icon: 'M5 4h14v16l-7-3-7 3z' },
  { key: 'speakers', label: 'Speakers', to: '/speakers', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0' },
  { key: 'delegates', label: 'Delegates', to: '/delegates', icon: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 19a6 6 0 0 1 12 0M17 11a3 3 0 1 0 0-6M15 13a6 6 0 0 1 7 6' },
  { key: 'exhibitors', label: 'Exhibitors', to: '/exhibitors', icon: 'M4 9l1-4h14l1 4M4 9v11h16V9M4 9h16M9 20v-6h6v6' },
  { key: 'meetings', label: 'Meetings', to: '/meetings', icon: 'M7 4v3M17 4v3M4 9h16M5 7h14v13H5z' },
  { key: 'lounge', label: 'Lounge', to: '/lounge', icon: 'M4 12v-2a3 3 0 0 1 6 0v2h4v-2a3 3 0 0 1 6 0v2M3 12h18v6H3zM6 18v2M18 18v2' },
  { key: 'sponsors', label: 'Sponsors', icon: 'M12 3l2.6 5.3 5.9.8-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.8z' },
  { key: 'rooms', label: 'Rooms', to: '/rooms', icon: 'M4 20V6l8-3 8 3v14M4 20h16M9 20v-5h6v5' },
  { key: 'contests', label: 'Contests', icon: 'M7 4h10v3a5 5 0 0 1-10 0zM7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M9 15h6l-1 5h-4z' },
]

const route = useRoute()
const site = useSiteStore()
const auth = useAuthStore()
const notifications = useNotificationsStore()
const chat = useChatStore()
const presence = usePresenceStore()
const briefcase = useBriefcaseStore()

const menuOpen = ref(false)
const bellOpen = ref(false)
const savedOpen = ref(false)
const qrOpen = ref(false)

const myInitials = computed(() => initials(auth.user?.name || site.name || 'U'))

function closeOnOutside(e: MouseEvent) {
  const t = e.target as HTMLElement
  if (!t?.closest?.('.user')) menuOpen.value = false
  if (!t?.closest?.('.bell-wrap')) bellOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', closeOnOutside)
  if (auth.user) {
    notifications.start()
    presence.start()
    if (!chat.loaded) chat.fetchInbox()
    briefcase.fetch()
  }
})
onBeforeUnmount(() => {
  document.removeEventListener('click', closeOnOutside)
  notifications.stop()
  presence.stop()
})

function toggleBell() {
  bellOpen.value = !bellOpen.value
  if (bellOpen.value) notifications.fetch()
}

const badge = (n: number) => (n > 99 ? '99+' : n)
</script>

<template>
  <header class="event-header">
    <div class="inner">
      <NuxtLink to="/reception" class="logo">
        <img v-if="site.logoUrl" :src="site.logoUrl" :alt="site.name" />
        <span v-else class="logo-badge">{{ (site.name || 'EV').slice(0, 3).toUpperCase() }}</span>
        <span class="logo-name">{{ site.name }}</span>
      </NuxtLink>

      <nav class="tabs" aria-label="Event sections">
        <template v-for="t in tabs" :key="t.key">
          <NuxtLink
            v-if="t.to"
            :to="t.to"
            class="tab"
            :class="{ active: route.path === t.to }"
          >
            <svg viewBox="0 0 24 24"><path :d="t.icon" /></svg>
            <span>{{ t.label }}</span>
          </NuxtLink>
          <button v-else type="button" class="tab disabled" title="Coming soon" disabled>
            <svg viewBox="0 0 24 24"><path :d="t.icon" /></svg>
            <span>{{ t.label }}</span>
          </button>
        </template>
      </nav>

      <nav class="utils" aria-label="Quick actions">
        <button class="util" type="button" title="Bookmarks" aria-label="Bookmarks" @click="savedOpen = true">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>

        <button class="util" type="button" title="Briefcase" aria-label="Briefcase" @click="briefcase.toggleDrawer()">
          <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
          <span v-if="briefcase.count" class="dot">{{ badge(briefcase.count) }}</span>
        </button>

        <button class="util" type="button" title="Leaderboard" aria-label="Leaderboard" disabled>
          <svg viewBox="0 0 24 24"><path d="M7 4h10v3a5 5 0 0 1-10 0zM7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M9 15h6l-1 5h-4z" /></svg>
        </button>

        <span class="bell-wrap">
          <button class="util" type="button" title="Notifications" aria-label="Notifications" @click.stop="toggleBell">
            <svg viewBox="0 0 24 24"><path d="M6 16V10a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" /></svg>
            <span v-if="notifications.unread" class="dot">{{ badge(notifications.unread) }}</span>
          </button>
          <EventNotificationsPanel v-if="bellOpen" @close="bellOpen = false" />
        </span>

        <button class="util" type="button" title="Chat" aria-label="Chat" @click="chat.toggleDrawer()">
          <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z" /></svg>
          <span v-if="chat.unreadTotal" class="dot">{{ badge(chat.unreadTotal) }}</span>
        </button>
      </nav>

      <ChatDrawer v-if="chat.drawerOpen" />
      <EventBriefcaseDrawer v-if="briefcase.drawerOpen" />
      <EventBookmarksPanel v-if="savedOpen" @close="savedOpen = false" />
      <EventQrModal v-if="qrOpen" @close="qrOpen = false" />

      <div class="user">
        <button class="user-btn" type="button" @click.stop="menuOpen = !menuOpen">
          <span class="avatar">{{ myInitials }}</span>
        </button>
        <div v-if="menuOpen" class="menu">
          <div class="menu-head">
            <strong>{{ auth.user?.name }}</strong>
            <small>{{ auth.user?.email }}</small>
          </div>
          <button type="button" class="menu-item" @click="qrOpen = true; menuOpen = false">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><path d="M14 14h3v3M21 14v.01M17 21h.01M21 17v4h-4" /></svg>
            My QR Code
          </button>
          <a href="#" class="menu-item" @click.prevent="auth.logout()">Logout</a>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.event-header { background: #fff; border-bottom: 1px solid #e6e8ec; box-shadow: 0 1px 2px rgba(15,23,42,.03); position: sticky; top: 0; z-index: 40; }
.inner { display: flex; align-items: center; gap: 10px; max-width: 1440px; margin: 0 auto; padding: 8px 18px; }

.logo { display: flex; align-items: center; gap: 10px; color: var(--brand-primary); flex: 0 0 auto; max-width: 220px; }
.logo img { max-height: 34px; max-width: 44px; object-fit: contain; }
.logo-badge { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 34px; border-radius: 6px; background: var(--brand-primary); color: #fff; font-weight: 800; font-size: .72rem; flex: 0 0 auto; }
.logo-name { font-weight: 700; color: #334155; font-size: .82rem; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }

.tabs { flex: 1; display: flex; align-items: stretch; justify-content: center; gap: 2px; overflow-x: auto; scrollbar-width: thin; }
.tabs::-webkit-scrollbar { height: 4px; }
.tabs::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.tab {
  flex: 0 0 auto; display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 8px 12px; min-width: 66px; border: none; background: none; cursor: pointer;
  color: #94a3b8; font: inherit; font-size: .64rem; font-weight: 600; letter-spacing: .2px;
  border-bottom: 3px solid transparent; white-space: nowrap;
}
.tab svg { width: 21px; height: 21px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.tab:not(.disabled):hover { color: var(--brand-primary); }
.tab.active { color: var(--brand-primary); border-bottom-color: var(--brand-primary); }
.tab.disabled { cursor: default; opacity: .85; }

.utils { display: flex; align-items: center; gap: 2px; flex: 0 0 auto; }
.bell-wrap { position: relative; display: inline-flex; }
.util { position: relative; background: none; border: none; padding: 7px; border-radius: 8px; cursor: pointer; color: var(--brand-primary); line-height: 0; }
.util:hover { background: #f1f2f6; }
.util:disabled { cursor: default; opacity: .5; }
.util:disabled:hover { background: none; }
.util svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.dot { position: absolute; top: 1px; right: 1px; min-width: 15px; height: 15px; padding: 0 3px; border-radius: 999px; background: #ef4444; color: #fff; font-size: .6rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }

.user { position: relative; flex: 0 0 auto; margin-left: 6px; }
.user-btn { display: flex; align-items: center; background: none; border: none; cursor: pointer; padding: 0; font: inherit; }
.avatar { width: 38px; height: 38px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: .85rem; }

.menu { position: absolute; right: 0; top: calc(100% + 8px); background: #fff; border: 1px solid #e6e8ec; border-radius: 12px; box-shadow: 0 12px 30px rgba(15,23,42,.12); min-width: 220px; overflow: hidden; }
.menu-head { padding: 12px 14px; border-bottom: 1px solid #f1f2f6; display: flex; flex-direction: column; gap: 2px; }
.menu-head small { color: #94a3b8; }
.menu-item { display: flex; align-items: center; gap: 10px; width: 100%; padding: 11px 14px; color: #334155; font: inherit; font-weight: 600; text-align: left; border: none; background: none; cursor: pointer; }
.menu-item:hover { background: #f7f8fa; color: var(--brand-primary); }
.menu-item svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

@media (max-width: 1100px) {
  .logo-name { display: none; }
}
</style>

<script setup lang="ts">
// Single-row event header: logo + title, section tabs, and utility/account
// icons all in one bar (replaces the old separate EventTopbar + EventNav).
interface Tab { key: string, label: string, to?: string, icon: string }

/**
 * Where each section lives and what it looks like. The *organizer* owns which
 * tabs appear, their order and their labels (admin › Navigation & Menu › Web App
 * Tabs); the app owns the route and the icon, because only it knows which pages
 * it actually ships. The two meet on `key`, which is stable — renaming a tab in
 * admin changes its label, never its key.
 *
 * A key we have no page for (ExpoLens, My Badges…) still renders, greyed out as
 * "Coming soon": the organizer switched it on and expects to see it, and quietly
 * dropping it would look like the setting didn't save.
 */
const TAB_META: Record<string, { to?: string, icon: string }> = {
  reception: { to: '/reception', icon: 'M4 20v-8l8-6 8 6v8h-6v-6h-4v6z' },
  // Admin slugs the label, so "EVENT FEED" arrives as `event_feed`; the app's
  // own default list has always called it `feed`. Both point at the same page.
  event_feed: { to: '/feed', icon: 'M4 5h16M4 12h16M4 19h10' },
  feed: { to: '/feed', icon: 'M4 5h16M4 12h16M4 19h10' },
  sessions: { to: '/sessions', icon: 'M5 4h14v16l-7-3-7 3z' },
  speakers: { to: '/speakers', icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM5 20a7 7 0 0 1 14 0' },
  delegates: { to: '/delegates', icon: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 19a6 6 0 0 1 12 0M17 11a3 3 0 1 0 0-6M15 13a6 6 0 0 1 7 6' },
  exhibitors: { to: '/exhibitors', icon: 'M4 9l1-4h14l1 4M4 9v11h16V9M4 9h16M9 20v-6h6v6' },
  meetings: { to: '/meetings', icon: 'M7 4v3M17 4v3M4 9h16M5 7h14v13H5z' },
  lounge: { to: '/lounge', icon: 'M4 12v-2a3 3 0 0 1 6 0v2h4v-2a3 3 0 0 1 6 0v2M3 12h18v6H3zM6 18v2M18 18v2' },
  rooms: { to: '/rooms', icon: 'M4 20V6l8-3 8 3v14M4 20h16M9 20v-5h6v5' },
  sponsors: { icon: 'M12 3l2.6 5.3 5.9.8-4.3 4.1 1 5.8-5.2-2.7-5.2 2.7 1-5.8-4.3-4.1 5.9-.8z' },
  contests: { icon: 'M7 4h10v3a5 5 0 0 1-10 0zM7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M9 15h6l-1 5h-4z' },
  expolens: { icon: 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16zM21 21l-4.3-4.3' },
  my_badges: { icon: 'M12 3l7 4v5c0 4.4-3 8.3-7 9-4-0.7-7-4.6-7-9V7z' },
}

/** A tab the organizer enabled that this build has never heard of. */
const FALLBACK_ICON = 'M4 6h16M4 12h16M4 18h16'

/** Used only when the organizer has never opened the Web App Tabs screen. */
const DEFAULT_TABS: { key: string, label: string }[] = [
  { key: 'reception', label: 'Reception' },
  { key: 'feed', label: 'Event Feed' },
  { key: 'sessions', label: 'Sessions' },
  { key: 'speakers', label: 'Speakers' },
  { key: 'delegates', label: 'Delegates' },
  { key: 'exhibitors', label: 'Exhibitors' },
  { key: 'meetings', label: 'Meetings' },
  { key: 'lounge', label: 'Lounge' },
  { key: 'sponsors', label: 'Sponsors' },
  { key: 'rooms', label: 'Rooms' },
  { key: 'contests', label: 'Contests' },
]

const route = useRoute()
const site = useSiteStore()

/** The organizer's tab bar, resolved against the pages this app ships. */
const tabs = computed<Tab[]>(() => {
  const configured = site.navigation?.tabs ?? []
  const source: { key: string, label: string }[] = configured.length ? configured : DEFAULT_TABS

  return source.map(t => ({
    key: t.key,
    label: t.label,
    to: TAB_META[t.key]?.to,
    icon: TAB_META[t.key]?.icon ?? FALLBACK_ICON,
  }))
})

const showTabIcons = computed(() => site.navigation?.icons ?? true)
const tabAlignment = computed(() => site.navigation?.alignment || 'left')

/**
 * The header modules the organizer left on (admin › Navigation & Menu ›
 * Modules): the brand block plus the quick actions. Everything defaults to on,
 * so an event configured before this existed — or never configured at all —
 * keeps the full header.
 *
 * A module that is off is not merely hidden: we also skip its polling and its
 * drawer below, so a switched-off chat isn't quietly fetching an inbox nobody
 * can open.
 */
const mod = (key: string) => site.navigation?.modules?.[key] !== false
const auth = useAuthStore()
const notifications = useNotificationsStore()
const chat = useChatStore()
const presence = usePresenceStore()
const briefcase = useBriefcaseStore()

const menuOpen = ref(false)
const bellOpen = ref(false)
const savedOpen = ref(false)
const qrOpen = ref(false)

function closeOnOutside(e: MouseEvent) {
  const t = e.target as HTMLElement
  if (!t?.closest?.('.user')) menuOpen.value = false
  if (!t?.closest?.('.bell-wrap')) bellOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', closeOnOutside)
  if (auth.user) {
    // Presence isn't a module — it's what makes the green dots work everywhere.
    presence.start()
    if (mod('notifications')) notifications.start()
    if (mod('chat') && !chat.loaded) chat.fetchInbox()
    if (mod('briefcase')) briefcase.fetch()
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
      <!-- Brand block: the organizer can drop the logo, the title, or both. -->
      <NuxtLink v-if="mod('event_logo') || mod('event_title')" to="/reception" class="logo">
        <template v-if="mod('event_logo')">
          <img v-if="site.logoUrl" :src="site.logoUrl" :alt="site.name" />
          <span v-else class="logo-badge">{{ (site.name || 'EV').slice(0, 3).toUpperCase() }}</span>
        </template>
        <span v-if="mod('event_title')" class="logo-name">{{ site.name }}</span>
      </NuxtLink>

      <nav class="tabs" :class="`align-${tabAlignment}`" aria-label="Event sections">
        <template v-for="t in tabs" :key="t.key">
          <NuxtLink
            v-if="t.to"
            :to="t.to"
            class="tab"
            :class="{ active: route.path === t.to }"
          >
            <svg v-if="showTabIcons" viewBox="0 0 24 24"><path :d="t.icon" /></svg>
            <span>{{ t.label }}</span>
          </NuxtLink>
          <button v-else type="button" class="tab disabled" title="Coming soon" disabled>
            <svg v-if="showTabIcons" viewBox="0 0 24 24"><path :d="t.icon" /></svg>
            <span>{{ t.label }}</span>
          </button>
        </template>
      </nav>

      <!-- Quick actions, per admin › Navigation & Menu › Modules. -->
      <nav class="utils" aria-label="Quick actions">
        <button v-if="mod('bookmark')" class="util" type="button" title="Bookmarks" aria-label="Bookmarks" @click="savedOpen = true">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>

        <button v-if="mod('briefcase')" class="util" type="button" title="Briefcase" aria-label="Briefcase" @click="briefcase.toggleDrawer()">
          <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
          <span v-if="briefcase.count" class="dot">{{ badge(briefcase.count) }}</span>
        </button>

        <button v-if="mod('leaderboard')" class="util" type="button" title="Leaderboard" aria-label="Leaderboard" disabled>
          <svg viewBox="0 0 24 24"><path d="M7 4h10v3a5 5 0 0 1-10 0zM7 5H4v2a3 3 0 0 0 3 3M17 5h3v2a3 3 0 0 1-3 3M9 15h6l-1 5h-4z" /></svg>
        </button>

        <span v-if="mod('notifications')" class="bell-wrap">
          <button class="util" type="button" title="Notifications" aria-label="Notifications" @click.stop="toggleBell">
            <svg viewBox="0 0 24 24">
              <path d="M6 16V10a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" />
            </svg>
            <span v-if="notifications.unread" class="dot">{{ badge(notifications.unread) }}</span>
          </button>
          <EventNotificationsPanel v-if="bellOpen" @close="bellOpen = false" />
        </span>

        <button v-if="mod('chat')" class="util" type="button" title="Chat" aria-label="Chat" @click="chat.toggleDrawer()">
          <svg viewBox="0 0 24 24"><path d="M4 5h16v11H8l-4 4z" /></svg>
          <span v-if="chat.unreadTotal" class="dot">{{ badge(chat.unreadTotal) }}</span>
        </button>
      </nav>

      <!-- A module that is off has no drawer either: another surface could have
           opened it (a delegate card's "Chat" button), and the attendee would be
           left with a panel the organizer switched off. -->
      <ChatDrawer v-if="mod('chat') && chat.drawerOpen" />
      <EventBriefcaseDrawer v-if="mod('briefcase') && briefcase.drawerOpen" />
      <EventBookmarksPanel v-if="mod('bookmark') && savedOpen" @close="savedOpen = false" />
      <EventQrModal v-if="qrOpen" @close="qrOpen = false" />

      <div class="user">
        <button class="user-btn" type="button" @click.stop="menuOpen = !menuOpen">
          <span class="avatar">
            <UserAvatar :name="auth.user?.name || site.name" />
          </span>
        </button>
        <div v-if="menuOpen" class="menu">
          <div class="menu-head">
            <strong>{{ auth.user?.name }}</strong>
            <small>{{ auth.user?.email }}</small>
          </div>
          <button type="button" class="menu-item" @click="qrOpen = true; menuOpen = false">
            <svg viewBox="0 0 24 24">
              <rect x="3" y="3" width="7" height="7" rx="1" />
              <rect x="14" y="3" width="7" height="7" rx="1" />
              <rect x="3" y="14" width="7" height="7" rx="1" />
              <path d="M14 14h3v3M21 14v.01M17 21h.01M21 17v4h-4" />
            </svg>
            My QR Code
          </button>
          <a href="#" class="menu-item" @click.prevent="auth.logout()">Logout</a>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.event-header {
  background: #fff;
  border-bottom: 1px solid #e6e8ec;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .03);
  position: sticky;
  top: 0;
  z-index: 40;
}

.inner {
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: 1440px;
  margin: 0 auto;
  padding: 8px 18px;
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--brand-primary);
  flex: 0 0 auto;
  max-width: 220px;
}

.logo img {
  max-height: 34px;
  max-width: 44px;
  object-fit: contain;
}

.logo-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 34px;
  border-radius: 6px;
  background: var(--brand-primary);
  color: #fff;
  font-weight: 800;
  font-size: .72rem;
  flex: 0 0 auto;
}

.logo-name {
  font-weight: 700;
  color: #334155;
  font-size: .82rem;
  line-height: 1.25;
  display: -webkit-box;
  -webkit-line-clamp: 1;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tabs { flex: 1; display: flex; align-items: stretch; justify-content: center; gap: 2px; overflow-x: auto; scrollbar-width: thin; }
/* Alignment of the tab row, per admin › Web App Tabs. */
.tabs.align-left { justify-content: flex-start; }
.tabs.align-center { justify-content: center; }
.tabs.align-right { justify-content: flex-end; }
.tabs.align-justify { justify-content: space-between; }
.tabs::-webkit-scrollbar { height: 4px; }
.tabs::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

.tab {
  flex: 0 0 auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 8px 12px;
  min-width: 66px;
  border: none;
  background: none;
  cursor: pointer;
  color: #94a3b8;
  font: inherit;
  font-size: .64rem;
  font-weight: 600;
  letter-spacing: .2px;
  border-bottom: 3px solid transparent;
  white-space: nowrap;
}

.tab svg {
  width: 21px;
  height: 21px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.tab:not(.disabled):hover {
  color: var(--brand-primary);
}

.tab.active {
  color: var(--brand-primary);
  border-bottom-color: var(--brand-primary);
}

.tab.disabled {
  cursor: default;
  opacity: .85;
}

.utils {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 0 0 auto;
}

.bell-wrap {
  position: relative;
  display: inline-flex;
}

.util {
  position: relative;
  background: none;
  border: none;
  padding: 7px;
  border-radius: 8px;
  cursor: pointer;
  color: var(--brand-primary);
  line-height: 0;
}

.util:hover {
  background: #f1f2f6;
}

.util:disabled {
  cursor: default;
  opacity: .5;
}

.util:disabled:hover {
  background: none;
}

.util svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.dot {
  position: absolute;
  top: 1px;
  right: 1px;
  min-width: 15px;
  height: 15px;
  padding: 0 3px;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: .6rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user {
  position: relative;
  flex: 0 0 auto;
  margin-left: 6px;
}

.user-btn {
  display: flex;
  align-items: center;
  background: none;
  border: none;
  cursor: pointer;
  padding: 0;
  font: inherit;
}

.avatar {
  width: 38px;
  height: 38px;
  border-radius: 50%;
  background: var(--brand-primary);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: .85rem;
}

.menu {
  position: absolute;
  right: 0;
  top: calc(100% + 8px);
  background: #fff;
  border: 1px solid #e6e8ec;
  border-radius: 12px;
  box-shadow: 0 12px 30px rgba(15, 23, 42, .12);
  min-width: 220px;
  overflow: hidden;
}

.menu-head {
  padding: 12px 14px;
  border-bottom: 1px solid #f1f2f6;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.menu-head small {
  color: #94a3b8;
}

.menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 11px 14px;
  color: #334155;
  font: inherit;
  font-weight: 600;
  text-align: left;
  border: none;
  background: none;
  cursor: pointer;
}

.menu-item:hover {
  background: #f7f8fa;
  color: var(--brand-primary);
}

.menu-item svg {
  width: 17px;
  height: 17px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

@media (max-width: 1100px) {
  .logo-name {
    display: none;
  }
}
</style>

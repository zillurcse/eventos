<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'

definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api   = useApi()
const id    = route.params.id as string

const slug = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
const mk   = (labels: string[]) => labels.map(l => ({ key: slug(l), label: l, enabled: true }))

const DEFAULT_TABS = ['RECEPTION', 'EVENT FEED', 'SESSIONS', 'SPEAKERS', 'DELEGATES', 'MEETINGS', 'LOUNGE', 'ExpoLens', 'ROOMS', 'EXHIBITORS', 'CONTESTS', 'MY BADGES']
const DEFAULT_FEED = ['All', 'Images', 'Video', 'Pdf', 'Polls', 'Offers', 'Looking For', 'My Posts']

const MODULES = [
  { key: 'event_logo',    label: 'Event Logo' },
  { key: 'event_title',   label: 'Event Title' },
  { key: 'briefcase',     label: 'Briefcase' },
  { key: 'chat',          label: 'Chat' },
  { key: 'notifications', label: 'Notifications' },
  { key: 'leaderboard',   label: 'Leaderboard' },
  { key: 'bookmark',      label: 'Bookmark' },
]

const nav = reactive<any>({
  web_app_tabs: { items: mk(DEFAULT_TABS), icons: true, background: true, alignment: 'left' },
  feed_tabs:    { items: mk(DEFAULT_FEED) },
  modules:      Object.fromEntries(MODULES.map(m => [m.key, true])),
  welcome_video: { type: 'youtube', url: '', show_after_login: false, show_on_home: false },
  nav_bar:      { icons: true, labels: true },
})

const saving = ref(false)
const saved  = ref(false)

async function load() {
  const n = (await api<any>(`/events/${id}/settings`)).data.navigation || {}
  if (n.web_app_tabs?.items?.length) Object.assign(nav.web_app_tabs, n.web_app_tabs)
  if (n.feed_tabs?.items?.length)    nav.feed_tabs.items = n.feed_tabs.items
  if (n.modules)                     Object.assign(nav.modules, n.modules)
  if (n.welcome_video)               Object.assign(nav.welcome_video, n.welcome_video)
  if (n.nav_bar)                     Object.assign(nav.nav_bar, n.nav_bar)
}

async function save() {
  saving.value = true
  try {
    await api(`/events/${id}/settings`, { method: 'PUT', body: { navigation: JSON.parse(JSON.stringify(nav)) } })
    saved.value = true
    setTimeout(() => (saved.value = false), 1500)
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>

    <!-- Page header -->
    <h1 class="text-[1.35rem] font-bold text-ink mb-6">Navigation &amp; Menu</h1>

    <!-- Sections -->
    <div class="card p-0! divide-y divide-line">
      <NavigationWebAppTabs
        :tabs="nav.web_app_tabs"
        @save="save"
      />

      <NavigationFeedTabs
        :tabs="nav.feed_tabs"
        @save="save"
      />

      <NavigationModules
        :modules="nav.modules"
        :list="MODULES"
        @change="save"
      />

      <NavigationWelcomeVideo
        :video="nav.welcome_video"
        @save="save"
      />

      <NavigationNavBar
        :nav-bar="nav.nav_bar"
        @change="save"
      />
    </div>

  </div>
</template>

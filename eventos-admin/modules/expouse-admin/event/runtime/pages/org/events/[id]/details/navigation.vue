<script setup lang="ts">
definePageMeta({ middleware: 'organizer', layout: 'event' })

const route = useRoute()
const api = useApi()
const id = route.params.id as string

const slug = (s: string) => s.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
const mk = (labels: string[]) => labels.map(l => ({ key: slug(l), label: l, enabled: true }))

const DEFAULT_TABS = ['RECEPTION', 'EVENT FEED', 'SESSIONS', 'SPEAKERS', 'DELEGATES', 'MEETINGS', 'LOUNGE', 'ExpoLens', 'ROOMS', 'EXHIBITORS', 'CONTESTS', 'MY BADGES']
const DEFAULT_FEED = ['All', 'Images', 'Video', 'Pdf', 'Polls', 'Offers', 'Looking For', 'My Posts']
const MODULES = [
  { key: 'event_logo', label: 'Event Logo' }, { key: 'event_title', label: 'Event Title' },
  { key: 'briefcase', label: 'Briefcase' }, { key: 'chat', label: 'Chat' },
  { key: 'notifications', label: 'Notifications' }, { key: 'leaderboard', label: 'Leaderboard' },
  { key: 'bookmark', label: 'Bookmark' },
]
const ALIGN = [
  { v: 'left', d: 'M3 6h18M3 12h12M3 18h15' },
  { v: 'center', d: 'M3 6h18M6 12h12M5 18h14' },
  { v: 'right', d: 'M3 6h18M9 12h12M6 18h15' },
  { v: 'justify', d: 'M3 6h18M3 12h18M3 18h18' },
]

const nav = reactive<any>({
  web_app_tabs: { items: mk(DEFAULT_TABS), icons: true, background: true, alignment: 'left' },
  feed_tabs: { items: mk(DEFAULT_FEED) },
  modules: Object.fromEntries(MODULES.map(m => [m.key, true])),
  welcome_video: { type: 'youtube', url: '', show_after_login: false, show_on_home: false },
})

const webOpen = ref(false)
const feedOpen = ref(false)
const welcomeOpen = ref(false)
const saved = ref(false)

async function load() {
  const n = (await api<any>(`/events/${id}/settings`)).data.navigation || {}
  if (n.web_app_tabs?.items?.length) Object.assign(nav.web_app_tabs, n.web_app_tabs)
  if (n.feed_tabs?.items?.length) nav.feed_tabs.items = n.feed_tabs.items
  if (n.modules) Object.assign(nav.modules, n.modules)
  if (n.welcome_video) Object.assign(nav.welcome_video, n.welcome_video)
}

async function save() {
  await api(`/events/${id}/settings`, { method: 'PUT', body: { navigation: JSON.parse(JSON.stringify(nav)) } })
  saved.value = true; setTimeout(() => (saved.value = false), 1500)
}

onMounted(load)
</script>

<template>
  <div class="card max-w-[880px]">
    <h2>Navigation &amp; Menu <span v-if="saved" class="badge active">saved ✓</span></h2>

    <!-- Web App Tabs -->
    <div class="flex items-center justify-between gap-4 py-[18px] border-b border-line">
      <div>
        <div class="font-bold text-base text-[#1a1a2e]">Web App Tabs</div>
        <div class="muted text-[.84rem]">Personalize the sections shown in your web app.</div>
      </div>
      <button class="btn ghost" @click="webOpen = true">MANAGE</button>
    </div>
    <div class="flex items-center justify-between gap-4 py-[18px] border-b border-line">
      <div>
        <div class="font-bold text-base text-[#1a1a2e]">Allowed feed tabs</div>
        <div class="muted text-[.84rem]">Choose the tabs shown on the feed page.</div>
      </div>
      <button class="btn ghost" @click="feedOpen = true">MANAGE</button>
    </div>

    <!-- Modules -->
    <div class="block py-[18px] border-b border-line">
      <div class="font-bold text-base text-[#1a1a2e] mb-3">Modules</div>
      <div class="flex flex-wrap gap-[18px_26px]">
        <label v-for="m in MODULES" :key="m.key" class="flex items-center gap-2 text-[.9rem] cursor-pointer m-0">
          <input v-model="nav.modules[m.key]" type="checkbox" class="w-[18px] h-[18px] m-0 accent-[#6352e7]" @change="save"> {{ m.label }}
        </label>
      </div>
    </div>

    <!-- Welcome video -->
    <div class="flex items-center justify-between gap-4 py-[18px]">
      <div>
        <div class="font-bold text-base text-[#1a1a2e]">Welcome Video</div>
        <div class="muted text-[.84rem]">Greet your attendees with a welcome video.</div>
      </div>
      <button class="btn ghost" @click="welcomeOpen = true">MANAGE</button>
    </div>

    <!-- ===== Web App Tabs modal ===== -->
    <Drawer v-if="webOpen" title="Web App Tabs" @close="webOpen = false">
      <div class="flex items-center gap-[18px] my-1 mb-4 flex-wrap">
        <button class="inline-flex items-center gap-2.5 bg-transparent border-0 cursor-pointer font-semibold text-[#475569] text-[.9rem] p-0" @click="nav.web_app_tabs.icons = !nav.web_app_tabs.icons">
          <span
            class="relative w-10 h-[22px] rounded-full bg-[#cdd2dc] shrink-0 transition-colors duration-[150ms]"
            :class="{ 'bg-[#6352e7]': nav.web_app_tabs.icons }"
          ><i
            class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-[150ms]"
            :class="{ 'translate-x-[18px]': nav.web_app_tabs.icons }"
          /></span> Icons
        </button>
        <button class="inline-flex items-center gap-2.5 bg-transparent border-0 cursor-pointer font-semibold text-[#475569] text-[.9rem] p-0" @click="nav.web_app_tabs.background = !nav.web_app_tabs.background">
          <span
            class="relative w-10 h-[22px] rounded-full bg-[#cdd2dc] shrink-0 transition-colors duration-[150ms]"
            :class="{ 'bg-[#6352e7]': nav.web_app_tabs.background }"
          ><i
            class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-[150ms]"
            :class="{ 'translate-x-[18px]': nav.web_app_tabs.background }"
          /></span> Background
        </button>
        <div class="flex-1" />
        <div class="inline-flex gap-1">
          <button
            v-for="a in ALIGN" :key="a.v"
            class="w-[34px] h-[34px] rounded-md border border-line bg-white grid place-items-center cursor-pointer text-[#64676A] hover:text-[#6352e7]"
            :class="{ 'bg-[#f3f0ff] border-[#6352e7] text-[#6352e7]': nav.web_app_tabs.alignment === a.v }"
            @click="nav.web_app_tabs.alignment = a.v"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path :d="a.d" /></svg>
          </button>
        </div>
      </div>
      <SortableList v-model="nav.web_app_tabs.items" editable />
      <div class="modal-actions"><button class="btn ghost" @click="webOpen = false">Close</button><button class="btn" @click="save(); webOpen = false">Save</button></div>
    </Drawer>

    <!-- ===== Feed Tabs modal ===== -->
    <Drawer v-if="feedOpen" title="Feed Tabs" @close="feedOpen = false">
      <SortableList v-model="nav.feed_tabs.items" />
      <div class="modal-actions"><button class="btn ghost" @click="feedOpen = false">Close</button><button class="btn" @click="save(); feedOpen = false">Save</button></div>
    </Drawer>

    <!-- ===== Welcome Video modal ===== -->
    <Drawer v-if="welcomeOpen" title="Manage Welcome Video" @close="welcomeOpen = false">
      <label>Video Type</label>
      <select v-model="nav.welcome_video.type">
        <option value="youtube">Youtube</option>
        <option value="vimeo">Vimeo</option>
        <option value="uploaded">Uploaded</option>
      </select>
      <label>{{ nav.welcome_video.type === 'youtube' ? 'Youtube URL' : nav.welcome_video.type === 'vimeo' ? 'Vimeo URL' : 'Video URL' }}</label>
      <input v-model="nav.welcome_video.url" placeholder="https://…">
      <h2 class="text-base m-0 mt-4 mb-2">Settings</h2>
      <button
        class="flex w-full items-center justify-between bg-transparent border-0 cursor-pointer font-semibold text-[#475569] text-[.9rem] py-2"
        @click="nav.welcome_video.show_after_login = !nav.welcome_video.show_after_login"
      >Show after login
        <span
          class="relative w-10 h-[22px] rounded-full bg-[#cdd2dc] shrink-0 transition-colors duration-[150ms]"
          :class="{ 'bg-[#6352e7]': nav.welcome_video.show_after_login }"
        ><i
          class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-[150ms]"
          :class="{ 'translate-x-[18px]': nav.welcome_video.show_after_login }"
        /></span>
      </button>
      <button
        class="flex w-full items-center justify-between bg-transparent border-0 cursor-pointer font-semibold text-[#475569] text-[.9rem] py-2"
        @click="nav.welcome_video.show_on_home = !nav.welcome_video.show_on_home"
      >Show on home screen
        <span
          class="relative w-10 h-[22px] rounded-full bg-[#cdd2dc] shrink-0 transition-colors duration-[150ms]"
          :class="{ 'bg-[#6352e7]': nav.welcome_video.show_on_home }"
        ><i
          class="absolute top-[3px] left-[3px] w-4 h-4 rounded-full bg-white transition-transform duration-[150ms]"
          :class="{ 'translate-x-[18px]': nav.welcome_video.show_on_home }"
        /></span>
      </button>
      <div class="modal-actions"><button class="btn ghost" @click="welcomeOpen = false">Close</button><button class="btn" @click="save(); welcomeOpen = false">SAVE</button></div>
    </Drawer>
  </div>
</template>

<script setup lang="ts">
const auth = useAuthStore()
const route = useRoute()
const api = useApi()

const id = computed(() => route.params.id as string)
const base = computed(() => `/org/events/${id.value}`)
const event = ref<any>(null)
const collapsed = ref(false)
const openKey = ref<string | null>(null)
const userOpen = ref(false)

const r = (p: string) => `${base.value}/${p}`
const kids = (pairs: [string, string][]) => pairs.map(([label, p]) => ({ label, to: r(p) }))

// EXPOUSE per-section icons (inner SVG markup).
const I: Record<string, string> = {
  overview: '<path d="M9.02 2.84l-5.39 4.2C2.73 7.74 2 9.23 2 10.36v7.41C2 20.92 4.13 23 6.73 23h10.54C19.87 23 22 20.92 22 17.77V10.5c0-1.21-.81-2.76-1.8-3.45l-6.21-4.33c-1.39-.97-3.65-.92-5 .12z"/><path d="M12 19v-3"/>',
  doc: '<path d="M21 7v10c0 3-1.5 5-5 5H8c-3.5 0-5-2-5-5V7c0-3 1.5-5 5-5h8c3.5 0 5 2 5 5z"/><path d="M14.5 4.5v2c0 1.1.9 2 2 2h2M8 13h4M8 17h8"/>',
  showcase: '<path d="M3.17 7.44L12 12.55l8.77-5.08M12 22.08V12.54"/><path d="M9.93 2.48L4.59 5.44c-1.21.67-2.2 2.35-2.2 3.73v5.65c0 1.38.99 3.06 2.2 3.73l5.34 2.97c1.14.63 3.01.63 4.15 0l5.34-2.97c1.21-.67 2.2-2.35 2.2-3.73V9.17c0-1.38-.99-3.06-2.2-3.73l-5.34-2.97c-1.15-.63-3.01-.63-4.15.01z"/>',
  content: '<path d="M3 6c0-1.1.9-2 2-2h14c1.1 0 2 .9 2 2v3H3V6z"/><path d="M3 11h8v9H5c-1.1 0-2-.9-2-2v-7zM13 11h8v7c0 1.1-.9 2-2 2h-6v-9z"/>',
  engagement: '<path d="M2 12.5h20M2 7.5h20M2 17.5h13"/>',
  comm: '<path d="M4 5h16v10H7l-3 3V5z"/><path d="M7 8h10M7 12h6"/>',
  onsite: '<path d="M12 13.43a3.12 3.12 0 100-6.24 3.12 3.12 0 000 6.24z"/><path d="M3.62 8.49c1.97-8.66 14.8-8.65 16.76.01 1.15 5.08-2.01 9.38-4.78 12.04a5.193 5.193 0 01-7.21 0c-2.76-2.66-5.92-6.97-4.77-12.05z"/>',
  services: '<path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21v-6h6v6M8 7.5h.01"/>',
  mail: '<path d="M17 20.5H7c-3 0-5-1.5-5-5v-7c0-3.5 2-5 5-5h10c3 0 5 1.5 5 5v7c0 3.5-2 5-5 5z"/><path d="m17 9-3.13 2.5c-1.03.82-2.72.82-3.75 0L7 9"/>',
  ads: '<path d="M22 12c0 5.52-4.48 10-10 10a9.97 9.97 0 01-8.21-4.28M2 12C2 6.48 6.48 2 12 2c3.7 0 6.93 2.01 8.68 5"/><path d="M17.5 13l2.49 2.49L22.5 13M7 9l2-5 2 5M7.5 7.5h3M6 12h4.5M13.5 5v7M16.5 5v7"/>',
  users: '<path d="M9 2C6.38 2 4.25 4.13 4.25 6.75c0 2.57 1.01 4.65 4.63 4.74h.29C11.54 11.37 13.5 9.29 13.5 6.75 13.5 4.13 11.38 2 9 2zM14.51 13.88c-2.62-1.75-6.89-1.75-9.52 0C3.73 14.7 3 15.84 3 17.07c0 1.22.72 2.35 1.97 3.17C6.32 21.07 7.66 21.5 9 21.5"/><path d="M17 15a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM22 21l-1.5-1.5"/>',
  expolens: '<path d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"/><path d="M3 9.5c0-1.1.9-2 2-2h1.5l1.2-2.1c.3-.5.9-.9 1.5-.9h5.6c.6 0 1.2.4 1.5.9l1.2 2.1H19c1.1 0 2 .9 2 2V17c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2V9.5z"/>',
  analytics: '<path d="M7 10.74v5.26M12 8v8M17 5v11"/><path d="M9 22H15c5 0 7-2 7-7V9c0-5-2-7-7-7H9C4 2 2 4 2 9v6c0 5 2 7 7 7z"/>',
  mobile: '<path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/><path d="M12 18h.01"/>',
  floor: '<path d="M2 22h20M3 22V8l7-6 7 6v14"/><path d="M9 22V15h3v7M14 22V13h3v9"/>',
  help: '<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>',
}
const wrap = (inner: string) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`

const sections = computed<any[]>(() => [
  { key: 'overview', label: 'Overview', svg: I.overview, to: base.value },
  { key: 'details', label: 'Event Details', svg: I.doc, children: [
    { label: 'General Information', to: r('details') },
    { label: 'Branding', to: r('details/branding') },
    { label: 'Navigation & Menu', to: r('details/navigation') },
  ] },
  { key: 'settings', label: 'Event Settings', svg: I.doc, children: [
    { label: 'Login Setup', to: r('settings') },
    { label: 'Form Builder', to: r('settings/forms') },
    { label: 'Domain', to: r('settings/domain') },
    { label: 'SEO & Meta Data', to: r('settings/seo') },
  ] },
  { key: 'showcase', label: 'Showcase Arena', svg: I.showcase, children: [
    { label: 'Manage filters', to: r('showcase/filters') },
    { label: 'Exhibitor Packages', to: r('showcase/packages') },
    { label: 'Exhibitors', to: r('showcase/exhibitors') },
    { label: 'Speakers', to: r('showcase/speakers') },
    { label: 'Sessions', to: r('showcase/sessions') },
  ] },
  { key: 'content', label: 'Content Hub', svg: I.content, children: kids([['Website Theme', 'content-hub/theme'], ['Publishing', 'content-hub/publishing'], ['Website Banners', 'content-hub/banners'], ['Social Links', 'content-hub/social'], ['Participant Profile', 'content-hub/profile'], ['Event Highlights', 'content-hub/highlights'], ['Image Gallery', 'content-hub/gallery'], ['FAQ', 'content-hub/faq'], ['Testimonials', 'content-hub/testimonials'], ['Blog', 'content-hub/blog']]) },
  { key: 'engagement', label: 'Event Engagement', svg: I.engagement, children: [
    { label: 'Bulk Notification', to: r('engagement/bulk-notification') },
    { label: 'Manage Activity Feed', to: r('engagement/activity-feed') },
    { label: 'Breakout Rooms', to: r('engagement/breakout-rooms') },
    { label: 'Contests', to: r('engagement/contests') },
    { label: 'Form Builder', to: r('settings/forms') },
    { label: 'Surveys', to: r('engagement/surveys') },
  ] },
  { key: 'communication', label: 'Communication', svg: I.comm, children: kids([['Communication', 'communication/functionality'], ['Profiles', 'communication/profiles'], ['Lounge', 'communication/lounge'], ['Meetings', 'communication/meetings'], ['Chats', 'communication/chats'], ['CTA', 'communication/cta'], ['Gamification', 'communication/gamification'], ['Notification', 'communication/notification']]) },
  { key: 'onsite', label: 'OnSite', svg: I.onsite, children: kids([['Badge templates', 'onsite/badge-templates'], ['Lead generation', 'onsite/lead-generation'], ['Gates Scanning', 'onsite/gates-scanning'], ['Exhibitors Scanning', 'onsite/exhibitors-scanning']]) },
  { key: 'services', label: 'Services', svg: I.services, children: kids([['Services', 'services/all'], ['Requested Services', 'services/requested']]) },
  { key: 'mail', label: 'Mail & Notification', svg: I.mail, children: kids([['Email Builder', 'mail/email-builder'], ['Sender Details', 'mail/sender-details'], ['Invite Mailer', 'mail/invite-mailer']]) },
  { key: 'ads', label: 'AD Managements', svg: I.ads, children: kids([['Manage ADs', 'ads/manage'], ['Insights', 'ads/insights']]) },
  { key: 'users', label: 'Users', svg: I.users, children: kids([['All Users', 'users/all'], ['WebApp users', 'users/webapp'], ['Blocked users', 'users/blocked']]) },
  { key: 'expolens', label: 'ExpoLens', svg: I.expolens, children: kids([['Photo Gallery', 'expolens/gallery'], ['Find Attendee Photos', 'expolens/find'], ['Moderate Uploads', 'expolens/moderate']]) },
  { key: 'floor', label: 'Floor Plan', svg: I.floor, to: r('floor') },
  { key: 'analytics', label: 'Analytics', svg: I.analytics, children: kids([['Website', 'analytics/website'], ['APP / Event platform', 'analytics/platform'], ['Marketing', 'analytics/marketing'], ['Survey', 'analytics/survey'], ['Ads', 'analytics/ads']]) },
  { key: 'mobile', label: 'Mobile App', svg: I.mobile, children: kids([['Help Screens', 'mobile/help-screens'], ['Manage Tabs', 'mobile/manage-tabs'], ['Branded Mobile App', 'mobile/branded-app'], ['Add App Banner', 'mobile/app-banner']]) },
])

function childActive(to: string) { return route.path === to }
function groupActive(item: any) { return item.children?.some((c: any) => childActive(c.to)) }

function toggle(item: any) {
  if (collapsed.value) collapsed.value = false
  openKey.value = openKey.value === item.key ? null : item.key
}
function toggleCollapse() {
  collapsed.value = !collapsed.value
  if (import.meta.client) localStorage.setItem('eventos_admin_sidebar_collapsed', collapsed.value ? '1' : '0')
}

// Accordion: open the group that matches the current route.
watch(() => route.fullPath, () => {
  const g = sections.value.find(s => s.children && groupActive(s))
  if (g) openKey.value = g.key
}, { immediate: true })

async function load() {
  try { event.value = (await api<any>(`/events/${id.value}`)).data } catch { /* */ }
}
async function publish() { await api(`/events/${id.value}/publish`, { method: 'POST' }); await load() }

const initials = computed(() => (event.value?.name || 'EV').split(/\s+/).map((s: string) => s[0]).slice(0, 2).join('').toUpperCase())
const userInitials = computed(() => (auth.user?.name || auth.user?.email || 'EE').split(/\s+/).map(s => s[0]).slice(0, 2).join('').toUpperCase())
function fmtDate(iso?: string) { return iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : '—' }

onMounted(() => {
  auth.init()
  if (auth.isAuthed && !auth.user) auth.fetchMe()
  if (import.meta.client) collapsed.value = localStorage.getItem('eventos_admin_sidebar_collapsed') === '1'
  load()
})
</script>

<template>
  <!-- ea2-root: flex min-h-screen bg-[#f5f6f8] -->
  <div class="flex min-h-screen bg-[#f5f6f8]">
    <!-- ea2-sidebar: bg-white border-r border-[#e8e8ef] flex flex-col sticky top-0 h-screen shrink-0 transition-[width] duration-[250ms] -->
    <aside
      class="bg-white border-r border-[#e8e8ef] flex flex-col sticky top-0 h-screen shrink-0 transition-[width] duration-[250ms]"
      :class="collapsed ? 'w-16' : 'w-[248px]'"
    >
      <!-- ea2-head: flex items-center justify-between px-[18px] py-4 border-b border-[#ebebf0] relative min-h-[60px] -->
      <div class="flex items-center justify-between px-[18px] py-4 border-b border-[#ebebf0] relative min-h-[60px]">
        <!-- ea2-brand: flex items-center gap-[9px] font-[800] text-[1.05rem] text-[#1a1a2e] tracking-[-0.02em] -->
        <NuxtLink v-if="!collapsed" :to="auth.home" class="flex items-center gap-[9px] font-[800] text-[1.05rem] text-[#1a1a2e] tracking-[-0.02em] no-underline">
          <!-- ea2-brand svg: color:#6352e7 -->
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="text-[#6352e7]"><rect x="2" y="9" width="3" height="13" rx="1"/><rect x="7" y="4" width="3" height="18" rx="1"/><rect x="12" y="11" width="3" height="11" rx="1"/><rect x="17" y="6" width="3" height="16" rx="1"/></svg>
          EventOS
        </NuxtLink>
        <!-- ea2-collapse: w-7 h-7 rounded-full bg-white border-none flex items-center justify-center cursor-pointer text-[#5f6b7a] shadow-[0_2px_10px_rgba(0,0,0,.10)] absolute right-[-14px] top-1/2 -translate-y-1/2 hover:bg-[#f5f5f8] -->
        <button
          class="w-7 h-7 rounded-full bg-white border-none flex items-center justify-center cursor-pointer text-[#5f6b7a] shadow-[0_2px_10px_rgba(0,0,0,.10)] absolute right-[-14px] top-1/2 -translate-y-1/2 hover:bg-[#f5f5f8]"
          :title="collapsed ? 'Expand' : 'Collapse'" @click="toggleCollapse"
        >
          <svg v-if="!collapsed" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6M9 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6M15 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </div>

      <!-- ea2-back: flex items-center gap-[7px] px-5 py-[10px] min-h-[42px] text-xs text-[#64676A] font-semibold transition-colors duration-[150ms] hover:text-[#6352e7] -->
      <!-- ea2-back.icon: justify-center px-0 border-b border-[#ebebf0] -->
      <NuxtLink
        to="/org/events"
        class="flex items-center gap-[7px] px-5 py-[10px] min-h-[42px] text-xs text-[#64676A] font-semibold transition-colors duration-[150ms] hover:text-[#6352e7] no-underline"
        :class="collapsed ? 'justify-center px-0 border-b border-[#ebebf0]' : ''"
        title="Back to events"
      >
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M19 12H5M5 12l7 7M5 12l7-7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span v-if="!collapsed">Back to events</span>
      </NuxtLink>

      <!-- ea2-nav: flex-1 py-2 overflow-y-auto overflow-x-hidden -->
      <nav class="ea2-nav flex-1 py-2 overflow-y-auto overflow-x-hidden">
        <template v-for="item in sections" :key="item.key">
          <!-- direct link -->
          <!-- ea2-item: my-px -->
          <div v-if="!item.children" class="my-px">
            <!-- ea2-link: flex items-center gap-3 px-5 py-[11px] min-h-[46px] text-sm font-semibold text-[#64676A] cursor-pointer transition-[background,color] duration-[150ms] whitespace-nowrap hover:bg-[#F7F7FB] hover:text-[#6352e7] -->
            <!-- ea2-item.active > ea2-link: bg-[#F7F7FB] text-[#6452E7] -->
            <NuxtLink
              :to="item.to"
              class="flex items-center gap-3 px-5 py-[11px] min-h-[46px] text-sm font-semibold text-[#64676A] cursor-pointer transition-[background,color] duration-[150ms] whitespace-nowrap hover:bg-[#F7F7FB] hover:text-[#6352e7] no-underline"
              :class="childActive(item.to) ? 'bg-[#F7F7FB] text-[#6452E7]' : ''"
              :title="collapsed ? item.label : ''"
            >
              <!-- ea2-ic: flex items-center shrink-0 w-[18px] -->
              <span class="flex items-center shrink-0 w-[18px]" v-html="wrap(item.svg)" />
              <!-- ea2-tx: flex-1 overflow-hidden text-ellipsis -->
              <span v-if="!collapsed" class="flex-1 overflow-hidden text-ellipsis">{{ item.label }}</span>
            </NuxtLink>
          </div>
          <!-- group -->
          <div v-else class="my-px">
            <div
              class="flex items-center gap-3 px-5 py-[11px] min-h-[46px] text-sm font-semibold cursor-pointer transition-[background,color] duration-[150ms] whitespace-nowrap hover:bg-[#F7F7FB] hover:text-[#6352e7] select-none"
              :class="groupActive(item) ? 'bg-[#F7F7FB] text-[#6452E7]' : 'text-[#64676A]'"
              :title="collapsed ? item.label : ''"
              @click="toggle(item)"
            >
              <span class="flex items-center shrink-0 w-[18px]" v-html="wrap(item.svg)" />
              <span v-if="!collapsed" class="flex-1 overflow-hidden text-ellipsis">{{ item.label }}</span>
              <!-- ea2-chev: shrink-0 transition-transform duration-[200ms] ml-auto -->
              <svg
                v-if="!collapsed"
                class="shrink-0 transition-transform duration-[200ms] ml-auto"
                :class="openKey === item.key ? 'rotate-180' : ''"
                width="12" height="12" viewBox="0 0 24 24" fill="none"
              ><path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
            <!-- ea2-sub: overflow-hidden transition-[max-height] duration-[250ms] ease pl-7 -->
            <div
              class="overflow-hidden transition-[max-height] duration-[250ms] ease-[ease] pl-7"
              :style="openKey === item.key && !collapsed ? 'max-height:600px' : 'max-height:0'"
            >
              <!-- ea2-sub-item: block py-2 px-[15px] text-sm text-[#7a8390] rounded-[5px] whitespace-nowrap transition-[background,color] duration-[150ms] hover:bg-[#f3f0ff] hover:text-[#6352e7] hover:font-semibold -->
              <!-- ea2-sub-item.active: bg-[#f3f0ff] text-[#6352e7] font-semibold -->
              <NuxtLink
                v-for="c in item.children" :key="c.to" :to="c.to"
                class="block py-2 px-[15px] text-sm text-[#7a8390] rounded-[5px] whitespace-nowrap transition-[background,color] duration-[150ms] no-underline hover:bg-[#f3f0ff] hover:text-[#6352e7] hover:font-semibold"
                :class="childActive(c.to) ? 'bg-[#f3f0ff] text-[#6352e7] font-semibold' : ''"
              >{{ c.label }}</NuxtLink>
            </div>
          </div>
        </template>

        <!-- ea2-sep: h-px bg-[#ebebf0] my-2.5 mx-3.5 -->
        <div class="h-px bg-[#ebebf0] my-2.5 mx-3.5" />
        <div class="my-px">
          <div
            class="flex items-center gap-3 px-5 py-[11px] min-h-[46px] text-sm font-semibold text-[#64676A] cursor-pointer transition-[background,color] duration-[150ms] whitespace-nowrap hover:bg-[#F7F7FB] hover:text-[#6352e7]"
            :title="collapsed ? 'Help' : ''"
          >
            <span class="flex items-center shrink-0 w-[18px]" v-html="wrap(I.help)" />
            <span v-if="!collapsed" class="flex-1 overflow-hidden text-ellipsis">Help</span>
          </div>
        </div>
      </nav>
    </aside>

    <!-- ea2-main: flex-1 min-w-0 flex flex-col -->
    <div class="flex-1 min-w-0 flex flex-col">
      <!-- ea2-header: bg-white h-[62px] flex items-center justify-between px-6 border-b border-[#e8e8ef] sticky top-0 z-10 -->
      <header class="bg-white h-[62px] flex items-center justify-between px-6 border-b border-[#e8e8ef] sticky top-0 z-10">
        <div class="flex items-center gap-3.5 min-w-0">
          <!-- ea2-thumb: w-10 h-10 rounded-lg overflow-hidden shrink-0 -->
          <div class="w-10 h-10 rounded-lg overflow-hidden shrink-0">
            <!-- ea2-thumb img: w-full h-full object-cover -->
            <img v-if="event?.cover_url" :src="event.cover_url" :alt="event.name" class="w-full h-full object-cover">
            <!-- ea2-thumb-ph: w-full h-full bg-[#1a1a2e] flex items-center justify-center text-[13px] font-bold text-white -->
            <div v-else class="w-full h-full bg-[#1a1a2e] flex items-center justify-center text-[13px] font-bold text-white">{{ initials }}</div>
          </div>
          <div class="min-w-0">
            <!-- ea2-ev-name: text-[15px] font-bold text-[#1a1a2e] m-0 leading-[1.3] -->
            <p class="text-[15px] font-bold text-[#1a1a2e] m-0 leading-[1.3]">{{ event?.name || 'Event' }}</p>
            <!-- ea2-ev-date: text-[11px] text-[#9aa0ab] m-0 leading-[1.3] -->
            <p class="text-[11px] text-[#9aa0ab] m-0 leading-[1.3]">{{ fmtDate(event?.starts_at) }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2.5">
          <!-- ea2-btn ea2-btn-out: inline-flex items-center px-4 py-[7px] rounded-lg text-[13px] font-semibold cursor-pointer whitespace-nowrap transition-[background,border-color] duration-[150ms] no-underline border-[1.5px] bg-white text-[#6352e7] border-[#d0caff] hover:bg-[#f3f0ff] hover:border-[#6352e7] -->
          <a
            class="inline-flex items-center px-4 py-[7px] rounded-lg text-[13px] font-semibold cursor-pointer whitespace-nowrap transition-[background,border-color] duration-[150ms] no-underline border-[1.5px] bg-white text-[#6352e7] border-[#d0caff] hover:bg-[#f3f0ff] hover:border-[#6352e7]"
            :href="`http://localhost:3001/events/${id}`" target="_blank"
          >Go to Event</a>
          <!-- ea2-btn ea2-btn-pri: inline-flex items-center px-4 py-[7px] rounded-lg text-[13px] font-semibold cursor-pointer whitespace-nowrap transition-[background,border-color] duration-[150ms] border-[1.5px] bg-[#6352e7] text-white border-[#6352e7] hover:bg-[#5242d6] -->
          <button
            v-if="event && event.status !== 'published'"
            class="inline-flex items-center px-4 py-[7px] rounded-lg text-[13px] font-semibold cursor-pointer whitespace-nowrap transition-[background,border-color] duration-[150ms] border-[1.5px] bg-[#6352e7] text-white border-[#6352e7] hover:bg-[#5242d6]"
            @click="publish"
          >Publish Event</button>
          <!-- ea2-published: text-[13px] font-semibold text-[#15803d] -->
          <span v-else-if="event" class="text-[13px] font-semibold text-[#15803d]">● Published</span>
          <div class="relative">
            <div class="flex items-center gap-2 cursor-pointer" @click="userOpen = !userOpen">
              <!-- ea2-avatar: w-[34px] h-[34px] rounded-full bg-[#ece9ff] text-[#5242d6] grid place-items-center font-bold text-[.78rem] -->
              <span class="w-[34px] h-[34px] rounded-full bg-[#ece9ff] text-[#5242d6] grid place-items-center font-bold text-[.78rem]">{{ userInitials }}</span>
              <!-- ea2-uname: text-[13px] font-semibold text-[#3a3e42] -->
              <span class="text-[13px] font-semibold text-[#3a3e42]">{{ auth.user?.name }}</span>
            </div>
            <!-- ea2-udrop: absolute top-[46px] right-0 bg-white rounded-lg shadow-[0_6px_24px_rgba(0,0,0,.12)] min-w-[160px] overflow-hidden z-[100] border border-[#ebebf0] -->
            <div v-if="userOpen" class="absolute top-[46px] right-0 bg-white rounded-lg shadow-[0_6px_24px_rgba(0,0,0,.12)] min-w-[160px] overflow-hidden z-[100] border border-[#ebebf0]" @click.stop>
              <div class="px-3.5 py-2 text-[.78rem] text-[#9aa0ab]">{{ auth.user?.email }}</div>
              <!-- ea2-udrop button: w-full text-left px-3.5 py-2.5 text-[13px] text-[#3a3e42] bg-transparent border-none border-t border-[#f0f0f5] cursor-pointer hover:bg-[#f5f5f8] -->
              <button class="w-full text-left px-3.5 py-2.5 text-[13px] text-[#3a3e42] bg-transparent border-none border-t border-[#f0f0f5] cursor-pointer font-[inherit] hover:bg-[#f5f5f8]" @click="auth.logout()">Sign out</button>
            </div>
          </div>
        </div>
      </header>

      <!-- ea2-content: p-6 pb-[60px] max-w-[1240px] w-full -->
      <main class="px-7 pt-6 pb-[60px] max-w-[1240px] w-full">
        <slot />
      </main>
    </div>
  </div>
</template>

<style>
.ea2-nav::-webkit-scrollbar { width: 4px; }
.ea2-nav::-webkit-scrollbar-thumb { background: #ebebf0; border-radius: 3px; }
</style>

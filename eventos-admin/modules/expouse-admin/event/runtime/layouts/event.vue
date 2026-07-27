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
  doc: '<path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9c.14.31.22.65.22 1H21a2 2 0 110 4h-.09c-.35 0-.69.08-1 .22z"/>',
  file: '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
  showcase: '<path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05M12 22.08V12"/>',
  content: '<path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/>',
  engagement: '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><path d="M17.5 14v7M14 17.5h7"/>',
  comm: '<path d="M17 20.5H7c-3 0-5-1.5-5-5v-7c0-3.5 2-5 5-5h10c3 0 5 1.5 5 5v7c0 3.5-2 5-5 5z"/><path d="m17 9-3.13 2.5c-1.03.82-2.72.82-3.75 0L7 9"/>',
  onsite: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>',
  services: '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>',
  mail: '<path d="M17 20.5H7c-3 0-5-1.5-5-5v-7c0-3.5 2-5 5-5h10c3 0 5 1.5 5 5v7c0 3.5-2 5-5 5z"/><path d="m17 9-3.13 2.5c-1.03.82-2.72.82-3.75 0L7 9"/>',
  ads: '<path d="M3 11c0-1.1.9-2 2-2h2l8-4v14l-8-4H5a2 2 0 01-2-2v-2z"/><path d="M11 5v14M15 9a3 3 0 010 6"/>',
  users: '<path d="M9 2C6.38 2 4.25 4.13 4.25 6.75c0 2.57 1.01 4.65 4.63 4.74h.29C11.54 11.37 13.5 9.29 13.5 6.75 13.5 4.13 11.38 2 9 2zM14.51 13.88c-2.62-1.75-6.89-1.75-9.52 0C3.73 14.7 3 15.84 3 17.07c0 1.22.72 2.35 1.97 3.17C6.32 21.07 7.66 21.5 9 21.5"/><path d="M17 15a2.5 2.5 0 100 5 2.5 2.5 0 000-5zM22 21l-1.5-1.5"/>',
  expolens: '<path d="M12 15.5a3.5 3.5 0 100-7 3.5 3.5 0 000 7z"/><path d="M3 9.5c0-1.1.9-2 2-2h1.5l1.2-2.1c.3-.5.9-.9 1.5-.9h5.6c.6 0 1.2.4 1.5.9l1.2 2.1H19c1.1 0 2 .9 2 2V17c0 1.1-.9 2-2 2H5c-1.1 0-2-.9-2-2V9.5z"/>',
  analytics: '<path d="M21.21 15.89A10 10 0 118 2.83"/><path d="M22 12A10 10 0 0012 2v10z"/>',
  mobile: '<path d="M17 2H7c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/><path d="M12 18h.01"/>',
  floor: '<path d="M2 22h20M3 22V8l7-6 7 6v14"/><path d="M9 22V15h3v7M14 22V13h3v9"/>',
  help: '<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3M12 17h.01"/>',
}
const wrap = (inner: string) => `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`

const sections = computed<any[]>(() => [
  { key: 'overview', label: 'Overview', svg: I.overview, to: r('overview') },
  { key: 'details', label: 'Event Details', svg: I.file, children: [
    { label: 'General Information', to: r('details/general-information') },
    { label: 'Branding', to: r('details/branding') },
    { label: 'Navigation & Menu', to: r('details/navigation') },
  ] },
  { key: 'settings', label: 'Event Settings', svg: I.doc, children: [
    { label: 'Login Setup', to: r('settings/login-setup') },
    { label: 'Profile', to: r('settings/profile') },
    { label: 'Form Builder', to: r('settings/forms') },
    { label: 'Domain', to: r('settings/domain') },
    { label: 'Video', to: r('settings/video') },
    { label: 'SEO & Meta Data', to: r('settings/seo') },
  ] },
  { key: 'showcase', label: 'Showcase Area', svg: I.showcase, children: [
    { label: 'Manage filters', to: r('showcase/filters') },
    { label: 'Exhibitor Packages', to: r('showcase/packages') },
    { label: 'Exhibitors', to: r('showcase/exhibitors') },
    { label: 'Speakers', to: r('showcase/speakers') },
    { label: 'Sessions', to: r('showcase/sessions') },
  ] },
  { key: 'content', label: 'Content Hub', svg: I.content, children: kids([['Website Theme', 'content-hub/theme'], ['Publishing', 'content-hub/publishing'], ['Website Banners', 'content-hub/banners'], ['Social Links', 'content-hub/social'], ['Participant Profile', 'content-hub/profile'], ['Event Highlights', 'content-hub/highlights'], ['Image Gallery', 'content-hub/gallery'], ['FAQ', 'content-hub/faq'], ['Testimonials', 'content-hub/testimonials'], ['Blog', 'content-hub/blog']]) },
  { key: 'engagement', label: 'Engagements', svg: I.engagement, children: [
    { label: 'Bulk Notification', to: r('engagement/bulk-notification') },
    { label: 'Manage Activity Feed', to: r('engagement/activity-feed') },
    { label: 'Breakout Rooms', to: r('engagement/breakout-rooms') },
    { label: 'Contests', to: r('engagement/contests') },
    { label: 'Form Builder', to: r('settings/forms') },
    { label: 'Surveys', to: r('engagement/surveys') },
  ] },
  { key: 'communication', label: 'Communication', svg: I.comm, children: kids([['Communication', 'communication/functionality'], ['Profiles', 'communication/profiles'], ['Lounge', 'communication/lounge'], ['Meetings', 'communication/meetings'], ['Chats', 'communication/chats'], ['CTA', 'communication/cta'], ['Gamification', 'communication/gamification'], ['Notification', 'communication/notification']]) },
  { key: 'onsite', label: 'Onsite', svg: I.onsite, children: kids([['Badge templates', 'onsite/badge-templates'], ['Lead generation', 'onsite/lead-generation'], ['Gates Scanning', 'onsite/gates-scanning'], ['Exhibitors Scanning', 'onsite/exhibitors-scanning']]) },
  { key: 'services', label: 'Services', svg: I.services, children: kids([['Services', 'services/all'], ['Requested Services', 'services/requested']]) },
  { key: 'mail', label: 'Mail & Notification', svg: I.mail, children: kids([['Emails', 'mail/emails'], ['Email Templates', 'mail/email-builder'], ['Sender Details', 'mail/sender-details'], ['Invite Mailer', 'mail/invite-mailer']]) },
  { key: 'ads', label: 'ADs-Management', svg: I.ads, children: kids([['Manage ADs', 'ads/manage'], ['Insights', 'ads/insights']]) },
  { key: 'users', label: 'Users', svg: I.users, children: kids([['All Users', 'users/all'], ['WebApp users', 'users/webapp'], ['Blocked users', 'users/blocked']]) },
  { key: 'expolens', label: 'ExpoLens', svg: I.expolens, children: kids([['Photo Gallery', 'expolens/gallery'], ['Find Attendee Photos', 'expolens/find'], ['Moderate Uploads', 'expolens/moderate']]) },
  { key: 'floor', label: 'Floor Plan', svg: I.floor, to: r('floor') },
  { key: 'analytics', label: 'Analytics', svg: I.analytics, children: kids([['Website', 'analytics/website'], ['APP / Event platform', 'analytics/platform'], ['Marketing', 'analytics/marketing'], ['Survey', 'analytics/survey'], ['Ads', 'analytics/ads']]) },
  { key: 'mobile', label: 'Mobile App', svg: I.mobile, children: kids([['Help Screens', 'mobile/help-screens'], ['Manage Tabs', 'mobile/manage-tabs'], ['Branded Mobile App', 'mobile/branded-app'], ['Add App Banner', 'mobile/app-banner']]) },
])

function childActive(to: string) { return route.path === to || route.path.startsWith(`${to}/`) }
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
        <NuxtLink  :to="auth.home" class="flex items-center gap-[9px] font-[800] text-[1.05rem] text-[#1a1a2e] tracking-[-0.02em] no-underline">
          <!-- ea2-brand svg: color:#6352e7 -->
          <!-- <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="text-[#6352e7]"><rect x="2" y="9" width="3" height="13" rx="1"/><rect x="7" y="4" width="3" height="18" rx="1"/><rect x="12" y="11" width="3" height="11" rx="1"/><rect x="17" y="6" width="3" height="16" rx="1"/></svg>
          EventOS -->

          <svg v-if="!collapsed" width="145" height="26" viewBox="0 0 145 26" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M17.5107 2.92969C16.7768 2.92969 16.1348 3.57053 16.1348 4.30293C16.1348 5.03532 16.7768 5.67617 17.5107 5.67617C18.2445 5.67617 18.8865 5.03532 18.8865 4.30293C18.8865 3.57053 18.2445 2.92969 17.5107 2.92969Z" fill="#6452E7"/>
              <path d="M17.5107 6.95752C16.7768 6.95752 16.1348 7.59836 16.1348 8.33076C16.1348 9.06315 16.7768 9.704 17.5107 9.704C18.2445 9.704 18.8865 9.06315 18.8865 8.33076C18.8865 7.50682 18.2445 6.95752 17.5107 6.95752Z" fill="#6452E7"/>
              <path d="M12.3738 7.41553C11.64 7.41553 10.9979 8.05637 10.9979 8.78877C10.9979 9.52116 11.64 10.162 12.3738 10.162C13.1076 10.162 13.7497 9.52116 13.7497 8.78877C13.7497 8.05637 13.1076 7.41553 12.3738 7.41553Z" fill="#6452E7"/>
              <path d="M12.3738 11.4438C11.64 11.4438 10.9979 12.0847 10.9979 12.8171C10.9979 13.5495 11.64 14.1903 12.3738 14.1903C13.1076 14.1903 13.7497 13.5495 13.7497 12.8171C13.7497 11.9931 13.1076 11.4438 12.3738 11.4438Z" fill="#6452E7"/>
              <path d="M7.23758 20.4155C6.50378 20.4155 5.86169 21.0564 5.86169 21.7888C5.86169 22.5212 6.50378 23.162 7.23758 23.162C7.97139 23.162 8.61347 22.5212 8.61347 21.7888C8.61347 20.9648 7.97139 20.4155 7.23758 20.4155Z" fill="#6452E7"/>
              <path d="M7.23758 16.3872C6.50378 16.3872 5.86169 17.0281 5.86169 17.7604C5.86169 18.4928 6.50378 19.1337 7.23758 19.1337C7.97139 19.1337 8.61347 18.4928 8.61347 17.7604C8.61347 17.0281 7.97139 16.3872 7.23758 16.3872Z" fill="#6452E7"/>
              <path d="M17.5102 11.0776C16.8681 11.0776 16.3177 11.6269 16.3177 12.2678V21.8805C16.3177 22.5213 16.7764 23.0706 17.4185 23.0706C17.6936 23.0706 18.0605 22.979 18.244 22.7044C18.4274 22.5213 18.6109 22.1551 18.6109 21.8805V12.2678C18.7026 11.6269 18.1523 11.0776 17.5102 11.0776Z" fill="#6452E7"/>
              <path d="M22.6473 5.95068C22.0052 5.95068 21.4548 6.49998 21.4548 7.14082V18.9507C21.4548 19.5915 22.0052 20.1408 22.6473 20.1408C23.2894 20.1408 23.8397 19.5915 23.8397 18.9507V7.14082C23.748 6.49998 23.2894 5.95068 22.6473 5.95068Z" fill="#6452E7"/>
              <path d="M12.3741 15.5635C11.732 15.5635 11.1816 16.1128 11.1816 16.7536V24.81C11.1816 25.4508 11.6403 26.0001 12.2824 26.0001C12.5575 26.0001 12.9244 25.9085 13.1079 25.6339C13.2913 25.3593 13.4748 25.0846 13.4748 24.81V16.7536C13.5665 16.1128 13.0162 15.5635 12.3741 15.5635Z" fill="#6452E7"/>
              <path d="M7.23767 14.9224C7.87975 14.9224 8.43011 14.3731 8.43011 13.7323V4.21114C8.43011 3.57029 7.97148 3.021 7.3294 3.021C7.05422 3.021 6.77904 3.11255 6.50387 3.38719C6.22869 3.57029 6.13696 3.84494 6.13696 4.11959V13.7323C6.13696 14.3731 6.59559 14.9224 7.23767 14.9224Z" fill="#6452E7"/>
              <path d="M12.3741 5.9507C13.0161 5.9507 13.5665 5.40141 13.5665 4.76056V1.19014C13.5665 0.549296 13.1079 0 12.4658 0C12.1906 0 11.9154 0.0915493 11.6402 0.366197C11.4568 0.549296 11.2733 0.915493 11.2733 1.19014V4.76056C11.1816 5.40141 11.732 5.9507 12.3741 5.9507Z" fill="#6452E7"/>
              <path d="M2.10071 5.95068C1.45863 5.95068 1 6.49998 1 7.14082V18.9507C1 19.5915 1.55036 20.1408 2.19244 20.1408C2.83452 20.1408 3.38487 19.5915 3.38487 18.9507V7.14082C3.29315 6.49998 2.74279 5.95068 2.10071 5.95068Z" fill="#6452E7"/>
              <path d="M74.0336 20.0498C72.0156 20.0498 70.4563 19.2258 69.3556 17.6695C68.53 16.3878 68.0714 14.8315 68.0714 13.092C68.0714 10.9864 68.6218 9.24695 69.8142 7.87371C70.8232 6.59202 72.1991 5.95117 74.0336 5.95117C76.0516 5.95117 77.6109 6.77512 78.7116 8.423C79.5371 9.70469 79.9958 11.261 79.9958 13.092C79.9958 15.1976 79.4454 16.9371 78.253 18.2188C77.1523 19.5005 75.7764 20.0498 74.0336 20.0498ZM74.0336 18.4019C75.4095 18.4019 76.4185 17.8526 77.0605 16.754C77.6109 15.8385 77.8861 14.6484 77.8861 13.1836C77.8861 11.4441 77.5192 10.0709 76.7854 9.1554C76.1433 8.2399 75.226 7.78216 74.0336 7.78216C72.7494 7.78216 71.7404 8.33145 71.0066 9.52159C70.4563 10.4371 70.1811 11.7188 70.1811 13.1836C70.1811 14.923 70.548 16.2047 71.2818 17.1202C71.9239 17.9441 72.8412 18.4019 74.0336 18.4019Z" fill="#6452E7"/>
              <path d="M39.5447 18.3101H33.8577V13.5495H38.5357C38.9943 13.5495 39.3612 13.1833 39.3612 12.7256C39.3612 12.2678 38.9943 11.9016 38.5357 11.9016H33.8577V7.69037H39.5447C40.0033 7.69037 40.3702 7.32417 40.3702 6.86642C40.3702 6.40868 40.0033 6.04248 39.5447 6.04248H31.8397V20.1411H39.5447C40.0033 20.1411 40.3702 19.7749 40.3702 19.3171C40.462 18.7678 40.0033 18.3101 39.5447 18.3101Z" fill="#6452E7"/>
              <path d="M60.4583 5.95117C59.3576 5.95117 58.1651 6.04272 56.881 6.22582V12.8174V19.0427C56.881 19.592 57.3396 20.0498 57.89 20.0498C58.4403 20.0498 58.899 19.592 58.899 19.0427V14.6484C59.4493 14.7399 60.0914 14.8315 60.7335 14.8315C62.0176 14.8315 63.1183 14.4653 64.0356 13.8244C65.0446 13.0005 65.5949 11.8103 65.5949 10.254C65.5949 7.41596 63.8521 5.95117 60.4583 5.95117ZM60.7335 13.1836C60.0914 13.1836 59.4493 13.092 58.899 13.0005V7.78216C59.3576 7.69061 59.9079 7.59906 60.55 7.59906C62.4763 7.59906 63.3935 8.51455 63.3935 10.254C63.3935 12.1765 62.4763 13.1836 60.7335 13.1836Z" fill="#6452E7"/>
              <path d="M93.6634 7.04976C93.6634 6.50047 93.2048 6.04272 92.6545 6.04272C92.1041 6.04272 91.6455 6.50047 91.6455 7.04976V8.69765V13.2751V14.3737C91.6455 17.0286 90.6365 18.3103 88.5268 18.3103C86.4171 18.3103 85.4081 17.0286 85.4081 14.3737V6.95821C85.4081 6.40892 84.9495 5.95117 84.3991 5.95117C83.8488 5.95117 83.3901 6.40892 83.3901 6.95821V13.2751V13.916C83.3901 18.0357 85.1329 20.1413 88.6185 20.1413C92.1041 20.1413 93.8469 18.0357 93.8469 13.916V13.2751V7.04976H93.6634Z" fill="#6452E7"/>
              <path d="M116.778 18.3101H111.091V13.5495H115.769C116.228 13.5495 116.595 13.1833 116.595 12.7256C116.595 12.2678 116.228 11.9016 115.769 11.9016H111.091V7.69037H116.778C117.237 7.69037 117.604 7.32417 117.604 6.86642C117.604 6.40868 117.237 6.04248 116.778 6.04248H109.073V20.1411H116.778C117.237 20.1411 117.604 19.7749 117.604 19.3171C117.696 18.7678 117.237 18.3101 116.778 18.3101Z" fill="#6452E7"/>
              <path d="M104.67 13.458C103.936 12.7256 102.652 12.0847 100.909 11.3523C99.7169 10.8946 99.0748 10.2537 99.0748 9.33825C99.0748 8.8805 99.2583 8.42276 99.7169 8.05656C100.176 7.69036 100.726 7.59881 101.46 7.59881C102.469 7.59881 103.478 7.87346 104.395 8.33121C104.762 8.42276 105.129 8.33121 105.312 7.96501C105.496 7.69036 105.404 7.41571 105.312 7.14107C105.312 7.04952 105.22 7.04952 105.22 7.04952C105.22 7.04952 105.129 7.04952 105.129 6.95797C104.028 6.22557 102.744 5.85938 101.368 5.85938C101.001 5.85938 100.634 5.85937 100.267 5.95092C99.5335 6.04247 98.7996 6.31712 98.2493 6.77487C97.4238 7.41571 96.9651 8.33121 96.9651 9.4298C96.9651 10.3453 97.332 11.1692 97.9741 11.8101C98.7079 12.4509 99.8086 13.0002 101.368 13.5495C102.836 14.0073 103.569 14.8312 103.569 16.0213C103.569 17.4861 102.652 18.2185 100.909 18.2185C99.9004 18.2185 98.8914 18.0354 98.0658 17.5777L97.332 17.1199C96.9651 16.9368 96.5065 17.0284 96.3231 17.3946C96.1396 17.7608 96.2313 18.2185 96.5982 18.4016L96.6899 18.4932C98.2493 19.5002 99.7169 19.958 101.093 19.958H101.643C102.56 19.8664 103.386 19.5918 104.12 19.134C105.129 18.4016 105.587 17.3946 105.587 16.1129C105.771 15.1059 105.404 14.1904 104.67 13.458Z" fill="#6452E7"/>
              <path d="M53.8539 19.5916L49.4511 12.8169L52.9367 7.69019L53.7622 6.50005C53.701 6.43901 53.6399 6.37798 53.5787 6.31695C52.9367 5.8592 52.0194 5.8592 51.5608 6.50005L48.3504 11.4437L45.2317 6.50005C45.14 6.4085 45.14 6.31695 45.0482 6.31695C44.4062 5.8592 43.4889 5.8592 43.0303 6.50005L47.0662 12.8169L42.3882 19.5916C42.4493 19.6526 42.5105 19.7137 42.5716 19.7747C43.2137 20.2324 44.131 20.2324 44.5896 19.5916L48.2586 14.0071L51.6525 19.5916C51.7136 19.6526 51.7748 19.7137 51.8359 19.7747C52.478 20.2324 53.3953 20.1409 53.8539 19.5916Z" fill="#6452E7"/>
          </svg>
          <svg v-else xmlns="http://www.w3.org/2000/svg" width="23" height="26" viewBox="0 0 23 26" fill="none">
              <path d="M16.5107 2.92969C15.7768 2.92969 15.1348 3.57053 15.1348 4.30293C15.1348 5.03532 15.7768 5.67617 16.5107 5.67617C17.2445 5.67617 17.8865 5.03532 17.8865 4.30293C17.8865 3.57053 17.2445 2.92969 16.5107 2.92969Z" fill="#6452E7"/>
              <path d="M16.5107 6.95752C15.7768 6.95752 15.1348 7.59836 15.1348 8.33076C15.1348 9.06315 15.7768 9.704 16.5107 9.704C17.2445 9.704 17.8865 9.06315 17.8865 8.33076C17.8865 7.50682 17.2445 6.95752 16.5107 6.95752Z" fill="#6452E7"/>
              <path d="M11.3738 7.41553C10.64 7.41553 9.99792 8.05637 9.99792 8.78877C9.99792 9.52116 10.64 10.162 11.3738 10.162C12.1076 10.162 12.7497 9.52116 12.7497 8.78877C12.7497 8.05637 12.1076 7.41553 11.3738 7.41553Z" fill="#6452E7"/>
              <path d="M11.3738 11.4438C10.64 11.4438 9.99792 12.0847 9.99792 12.8171C9.99792 13.5495 10.64 14.1903 11.3738 14.1903C12.1076 14.1903 12.7497 13.5495 12.7497 12.8171C12.7497 11.9931 12.1076 11.4438 11.3738 11.4438Z" fill="#6452E7"/>
              <path d="M6.23758 20.4155C5.50378 20.4155 4.86169 21.0564 4.86169 21.7888C4.86169 22.5212 5.50378 23.162 6.23758 23.162C6.97139 23.162 7.61347 22.5212 7.61347 21.7888C7.61347 20.9648 6.97139 20.4155 6.23758 20.4155Z" fill="#6452E7"/>
              <path d="M6.23758 16.3872C5.50378 16.3872 4.86169 17.0281 4.86169 17.7604C4.86169 18.4928 5.50378 19.1337 6.23758 19.1337C6.97139 19.1337 7.61347 18.4928 7.61347 17.7604C7.61347 17.0281 6.97139 16.3872 6.23758 16.3872Z" fill="#6452E7"/>
              <path d="M16.5102 11.0776C15.8681 11.0776 15.3177 11.6269 15.3177 12.2678V21.8805C15.3177 22.5213 15.7764 23.0706 16.4185 23.0706C16.6936 23.0706 17.0605 22.979 17.244 22.7044C17.4274 22.5213 17.6109 22.1551 17.6109 21.8805V12.2678C17.7026 11.6269 17.1523 11.0776 16.5102 11.0776Z" fill="#6452E7"/>
              <path d="M21.6473 5.95068C21.0052 5.95068 20.4548 6.49998 20.4548 7.14082V18.9507C20.4548 19.5915 21.0052 20.1408 21.6473 20.1408C22.2894 20.1408 22.8397 19.5915 22.8397 18.9507V7.14082C22.748 6.49998 22.2894 5.95068 21.6473 5.95068Z" fill="#6452E7"/>
              <path d="M11.3741 15.5635C10.732 15.5635 10.1816 16.1128 10.1816 16.7536V24.81C10.1816 25.4508 10.6403 26.0001 11.2824 26.0001C11.5575 26.0001 11.9244 25.9085 12.1079 25.6339C12.2913 25.3593 12.4748 25.0846 12.4748 24.81V16.7536C12.5665 16.1128 12.0162 15.5635 11.3741 15.5635Z" fill="#6452E7"/>
              <path d="M6.23767 14.9224C6.87975 14.9224 7.43011 14.3731 7.43011 13.7323V4.21114C7.43011 3.57029 6.97148 3.021 6.3294 3.021C6.05422 3.021 5.77904 3.11255 5.50387 3.38719C5.22869 3.57029 5.13696 3.84494 5.13696 4.11959V13.7323C5.13696 14.3731 5.59559 14.9224 6.23767 14.9224Z" fill="#6452E7"/>
              <path d="M11.3741 5.9507C12.0161 5.9507 12.5665 5.40141 12.5665 4.76056V1.19014C12.5665 0.549296 12.1079 0 11.4658 0C11.1906 0 10.9154 0.0915493 10.6402 0.366197C10.4568 0.549296 10.2733 0.915493 10.2733 1.19014V4.76056C10.1816 5.40141 10.732 5.9507 11.3741 5.9507Z" fill="#6452E7"/>
              <path d="M1.10071 5.95068C0.458629 5.95068 0 6.49998 0 7.14082V18.9507C0 19.5915 0.550355 20.1408 1.19244 20.1408C1.83452 20.1408 2.38487 19.5915 2.38487 18.9507V7.14082C2.29315 6.49998 1.74279 5.95068 1.10071 5.95068Z" fill="#6452E7"/>
          </svg>

        </NuxtLink>
        <!-- ea2-collapse: w-7 h-7 rounded-full bg-white border-none flex items-center justify-center cursor-pointer text-[#5f6b7a] shadow-[0_2px_10px_rgba(0,0,0,.10)] absolute right-[-14px] top-1/2 -translate-y-1/2 hover:bg-[#f5f5f8] -->
        <button
          class=" flex items-center justify-center cursor-pointer text-[#5f6b7a]"
          :title="collapsed ? 'Expand' : 'Collapse'" @click="toggleCollapse"
        >
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" :class="collapsed ? 'rotate-180 ml-0.5' : 'rotate-0'">
              <path d="M11 17L6 12L11 7" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M18 17L13 12L18 7" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <!-- <svg v-if="!collapsed" width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M15 18l-6-6 6-6M9 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
          <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 18l6-6-6-6M15 18l6-6-6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg> -->
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
          <div
            v-else
            class="my-1 overflow-hidden transition-colors duration-[150ms]"
            :class="groupActive(item) ? 'bg-[#F7F7FB]' : ''"
          >
            <div
              class="flex items-center gap-3 px-5 py-[11px] min-h-[46px] text-sm font-semibold cursor-pointer transition-colors duration-[150ms] whitespace-nowrap select-none"
              :class="groupActive(item) ? 'text-[#6352e7]' : 'text-[#64676A] hover:bg-[#F7F7FB] hover:text-[#6352e7] rounded-xl'"
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
            <!-- ea2-sub: overflow-hidden transition-[max-height] duration-[250ms] ease pl-[31px] -->
            <div
              class="overflow-hidden transition-[max-height] duration-[250ms] ease-[ease] pl-[42px]"
              :style="openKey === item.key && !collapsed ? 'max-height:600px' : 'max-height:0'"
            >
              <!-- ea2-sub-item: block py-2 pr-[15px] text-sm text-[#7a8390] whitespace-nowrap transition-colors duration-[150ms] hover:text-[#6352e7] -->
              <!-- ea2-sub-item.active: text-[#6352e7] font-semibold -->
              <NuxtLink
                v-for="c in item.children" :key="c.to" :to="c.to"
                class="block py-2 pr-[15px] text-sm text-[#7a8390] font-semibold whitespace-nowrap transition-colors duration-[150ms] no-underline hover:text-[#6352e7]"
                :class="childActive(c.to) ? 'text-[#6352e7]' : ''"
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
              <!-- ea2-udrop item: w-full text-left px-3.5 py-2.5 text-[13px] text-[#3a3e42] bg-transparent border-t border-[#f0f0f5] cursor-pointer hover:bg-[#f5f5f8] -->
              <NuxtLink to="/account" class="block w-full text-left px-3.5 py-2.5 text-[13px] text-[#3a3e42] no-underline border-t border-[#f0f0f5] cursor-pointer hover:bg-[#f5f5f8]" @click="userOpen = false">Profile</NuxtLink>
              <button class="w-full text-left px-3.5 py-2.5 text-[13px] text-[#3a3e42] bg-transparent border-none border-t border-[#f0f0f5] cursor-pointer font-[inherit] hover:bg-[#f5f5f8]" @click="auth.logout()">Sign out</button>
            </div>
          </div>
        </div>
      </header>

      <!-- ea2-content: p-6 pb-[60px] max-w-[1240px] w-full -->
      <main class="px-7 pt-6 pb-[60px] w-full">
        <slot />
      </main>
    </div>
  </div>
</template>

<style>
.ea2-nav::-webkit-scrollbar { width: 4px; }
.ea2-nav::-webkit-scrollbar-thumb { background: #ebebf0; border-radius: 3px; }
</style>

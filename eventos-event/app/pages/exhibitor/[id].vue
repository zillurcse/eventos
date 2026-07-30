<script setup lang="ts">
// @ts-expect-error project already uses vue-sonner; this file hits a local ts-plugin false positive
import { toast } from 'vue-sonner'
import { briefcaseKind } from '~/stores/briefcase'
import type { ExhibitorCta } from '~/stores/exhibitors'

definePageMeta({ layout: 'event', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const api = useApi()
const store = useExhibitorsStore()
const contact = useExhibitorContactStore()
const bookmarks = useBookmarksStore()
const briefcase = useBriefcaseStore()
const site = useSiteStore()
const auth = useAuthStore()
const meetings = useMeetingsStore()
const chat = useChatStore()

function docKind(url?: string | null) {
  return url ? briefcaseKind(url) : 'file'
}
function docKindLabel(url?: string | null) {
  return ({ pdf: 'PDF FILE', doc: 'DOC FILE', excel: 'EXCEL FILE', image: 'IMAGE' } as Record<string, string>)[docKind(url)] || 'FILE'
}

const id = computed(() => route.params.id as string)
const ex = computed(() => store.detail)
const rating = ref(0)
const hoverRating = ref(0)
const ratingSaving = ref(false)

async function loadRating() {
  if (!site.event?.uuid || !ex.value?.id || !auth.isAuthed) return
  try {
    const res = await api<{ data: { score: number | null } }>(`/events/${site.event.uuid}/exhibitors/${ex.value.id}/rating`)
    rating.value = res.data.score || 0
  } catch {
    rating.value = 0
  }
}

async function setRating(n: number) {
  if (!site.event?.uuid || !ex.value?.id || ratingSaving.value) return
  ratingSaving.value = true
  const prev = rating.value
  rating.value = n
  try {
    const res = await api<{ data: { score: number } }>(`/events/${site.event.uuid}/exhibitors/${ex.value.id}/rating`, {
      method: 'POST',
      body: { score: n },
    })
    rating.value = res.data.score
    toast.success(`You rated this exhibitor ${res.data.score} out of 5.`)
  } catch (e: any) {
    rating.value = prev
    toast.error(e?.data?.message || 'Could not save your rating.')
  } finally {
    ratingSaving.value = false
  }
}

onMounted(() => {
  store.fetchDetail(id.value)
  bookmarks.fetch()
  meetings.fetchCapabilities({ force: true })
  if (auth.isAuthed) chat.fetchCapabilities()
})
watch(id, (v: string) => {
  if (!v) return
  store.fetchDetail(v)
  hoverRating.value = 0
  rating.value = 0
}, { immediate: true })
watch([() => site.event?.uuid, () => ex.value?.id, () => auth.isAuthed], ([eventUuid, exhibitorId, authed]: [string | undefined, string | undefined, boolean]) => {
  if (!eventUuid || !exhibitorId || !authed) {
    rating.value = 0
    return
  }
  loadRating()
}, { immediate: true })

const bookmarked = computed(() => bookmarks.isOn('exhibitor', id.value))
async function toggleBookmark() {
  const was = bookmarked.value
  await bookmarks.toggle('exhibitor', id.value)
  if (bookmarked.value !== was) toast.success(bookmarked.value ? 'Added to your bookmarks.' : 'Removed from your bookmarks.')
  else toast.error('Could not update your bookmark.')
}
const exhibitorRole = computed(() => ex.value?.type === 'sponsor' ? 'sponsor' : 'exhibitor')
const chatEnabled = computed(() =>
  auth.isAuthed
  && site.chatModuleEnabled
  && chat.canChatRole(exhibitorRole.value),
)
const meetEnabled = computed(() => {
  return auth.isAuthed
    && site.meetingsTabEnabled
    && meetings.canRequest
    && meetings.canMeetRole(exhibitorRole.value)
})

function openChat() {
  if (!chatEnabled.value || !ex.value) return
  contact.openFor({
    id: ex.value.id,
    name: ex.value.name,
    logo_url: ex.value.logo_url,
    booth: ex.value.booth,
    type: ex.value.type,
    category: ex.value.category,
  }, 'chat')
}
function openMeet() {
  if (!meetEnabled.value || !ex.value) return
  contact.openFor({
    id: ex.value.id,
    name: ex.value.name,
    logo_url: ex.value.logo_url,
    booth: ex.value.booth,
    type: ex.value.type,
    category: ex.value.category,
  }, 'meet')
}
function openShareDetails() {
  if (chatEnabled.value) openChat()
  else if (meetEnabled.value) openMeet()
}

const copied = ref(false)
async function share() {
  const url = window.location.href
  try {
    if (navigator.share) await navigator.share({ title: ex.value?.name, url })
    else { await navigator.clipboard.writeText(url); copied.value = true; setTimeout(() => (copied.value = false), 1500) }
  } catch { /* dismissed */ }
}

// Social icons — the API only ever sends the platforms it actually collects
// (linkedin, twitter, facebook, instagram), so unknown keys are ignored and
// missing ones simply don't render. Shown left→right in this fixed order.
const SOCIAL_ORDER = ['facebook', 'instagram', 'whatsapp', 'twitter', 'linkedin', 'youtube'] as const
const socialIcons: Record<string, string> = {
  facebook: '<svg xmlns="http://www.w3.org/2000/svg" width="13" height="22" viewBox="0 0 13 22" fill="none"> <path d="M12 1H9C7.67392 1 6.40215 1.52678 5.46447 2.46447C4.52678 3.40215 4 4.67392 4 6V9H1V13H4V21H8V13H11L12 9H8V6C8 5.73478 8.10536 5.48043 8.29289 5.29289C8.48043 5.10536 8.73478 5 9 5H12V1Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> </svg>',
  instagram: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"> <path d="M17 2H7C4.23858 2 2 4.23858 2 7V17C2 19.7614 4.23858 22 7 22H17C19.7614 22 22 19.7614 22 17V7C22 4.23858 19.7614 2 17 2Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <path d="M16 11.3703C16.1234 12.2025 15.9812 13.0525 15.5937 13.7993C15.2062 14.5461 14.5931 15.1517 13.8416 15.53C13.0901 15.9082 12.2384 16.0399 11.4077 15.9062C10.5771 15.7726 9.80971 15.3804 9.21479 14.7855C8.61987 14.1905 8.22768 13.4232 8.09402 12.5925C7.96035 11.7619 8.09202 10.9102 8.47028 10.1587C8.84854 9.40716 9.45414 8.79404 10.2009 8.40654C10.9477 8.01904 11.7977 7.87689 12.63 8.0003C13.4789 8.12619 14.2648 8.52176 14.8716 9.12861C15.4785 9.73545 15.8741 10.5214 16 11.3703Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <path d="M17.5 6.5H17.51" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> </svg>',
  whatsapp: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 22 22" fill="none"> <path d="M16.0402 13.1826C15.7672 13.0461 14.4209 12.386 14.17 12.2958C13.9191 12.2045 13.7367 12.1594 13.5544 12.4322C13.372 12.704 12.847 13.319 12.6867 13.5005C12.5275 13.6821 12.3673 13.7041 12.0943 13.5677C11.2874 13.2479 10.5428 12.7907 9.89359 12.2166C9.29487 11.6657 8.78158 11.0294 8.37048 10.3285C8.21131 10.0557 8.3539 9.90826 8.49096 9.77293C8.61365 9.6508 8.76507 9.45496 8.90103 9.29542C9.01363 9.15769 9.10593 9.00469 9.17514 8.84102C9.21156 8.76581 9.22852 8.68273 9.22446 8.59934C9.22041 8.51594 9.19548 8.43488 9.15193 8.36352C9.0834 8.22709 8.53628 6.88589 8.30858 6.34017C8.08642 5.80986 7.86093 5.88137 7.69182 5.87257C7.53266 5.86487 7.35028 5.86267 7.16791 5.86267C7.02917 5.86652 6.89274 5.89891 6.7672 5.9578C6.64165 6.01668 6.52971 6.1008 6.4384 6.20485C6.12895 6.49656 5.88392 6.84923 5.71893 7.2404C5.55394 7.63157 5.4726 8.05266 5.48011 8.47684C5.56886 9.50473 5.95724 10.4844 6.59757 11.2957C7.77131 13.0473 9.38253 14.465 11.273 15.4095C11.783 15.6275 12.3039 15.8192 12.8337 15.9838C13.3923 16.1526 13.9827 16.1891 14.558 16.0905C14.939 16.0137 15.2998 15.859 15.6177 15.6363C15.9356 15.4136 16.2037 15.1277 16.4049 14.7966C16.5845 14.3895 16.6398 13.9388 16.5641 13.5005C16.4967 13.3861 16.3143 13.319 16.0402 13.1826V13.1826ZM18.7946 3.19349C16.9155 1.32346 14.4178 0.196881 11.7664 0.0234775C9.11503 -0.149926 6.49069 0.641668 4.38197 2.25088C2.27324 3.8601 0.823849 6.17728 0.303621 8.77101C-0.216607 11.3647 0.227784 14.0583 1.55406 16.3502L0 21.9999L5.80728 20.4849C7.41346 21.3554 9.21315 21.8116 11.042 21.8118H11.0464C13.2129 21.8107 15.3305 21.1704 17.1316 19.9718C18.9327 18.7733 20.3365 17.0702 21.1656 15.0778C21.9948 13.0854 22.2121 10.8931 21.7901 8.77785C21.3681 6.66258 20.3257 4.71928 18.7946 3.19348V3.19349ZM15.8843 18.5847C14.4343 19.4895 12.7577 19.9695 11.0464 19.9699H11.042C9.41157 19.9699 7.81118 19.5332 6.40856 18.7058L6.07586 18.5099L2.62952 19.4099L3.54914 16.0652L3.3336 15.7219C2.37701 14.2034 1.89425 12.4366 1.94637 10.645C1.99849 8.85342 2.58315 7.11742 3.62642 5.65656C4.66969 4.1957 6.12471 3.07559 7.80749 2.43786C9.49027 1.80013 11.3252 1.67342 13.0804 2.07377C14.8355 2.47411 16.4319 3.38352 17.6678 4.68701C18.9037 5.99049 19.7236 7.6295 20.0237 9.39679C20.3239 11.1641 20.0908 12.9803 19.354 14.6157C18.6172 16.2512 17.4097 17.6324 15.8843 18.5847" fill="#64676A"/> </svg>',
  twitter: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="18" viewBox="0 0 20 18" fill="none"><path d="M15.7512 0H18.818L12.1179 7.62462L20 18H13.8284L8.99458 11.7074L3.46359 18H0.394938L7.5613 9.84461L0 0H6.32828L10.6976 5.75169L15.7512 0ZM14.6748 16.1723H16.3742L5.4049 1.73169H3.58133L14.6748 16.1723Z" fill="#64676A"/></svg>',
  linkedin: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"> <path d="M6 9H2V21H6V9Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <path d="M16 8C17.5913 8 19.1174 8.63214 20.2426 9.75736C21.3679 10.8826 22 12.4087 22 14V21H18V14C18 13.4696 17.7893 12.9609 17.4142 12.5858C17.0391 12.2107 16.5304 12 16 12C15.4696 12 14.9609 12.2107 14.5858 12.5858C14.2107 12.9609 14 13.4696 14 14V21H10V14C10 12.4087 10.6321 10.8826 11.7574 9.75736C12.8826 8.63214 14.4087 8 16 8V8Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <path d="M4 6C5.10457 6 6 5.10457 6 4C6 2.89543 5.10457 2 4 2C2.89543 2 2 2.89543 2 4C2 5.10457 2.89543 6 4 6Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> </svg>',
  youtube: '<svg xmlns="http://www.w3.org/2000/svg" width="21" height="15" viewBox="0 0 21 15" fill="none"> <path d="M20.1747 4.99738C20.2201 3.68531 19.9331 2.38306 19.3406 1.21154C18.9385 0.730842 18.3806 0.406444 17.7639 0.294876C15.2133 0.0634411 12.6522 -0.0314165 10.0914 0.0107097C7.54001 -0.0333285 4.98806 0.0584682 2.44641 0.28571C1.94392 0.377117 1.47889 0.612815 1.10808 0.964043C0.283081 1.72488 0.191415 3.02654 0.0997479 4.12654C-0.0332493 6.10431 -0.0332493 8.08878 0.0997479 10.0665C0.126267 10.6857 0.218451 11.3002 0.374748 11.8999C0.485275 12.3629 0.708894 12.7912 1.02558 13.1465C1.39891 13.5164 1.87477 13.7655 2.39141 13.8615C4.36767 14.1055 6.35895 14.2066 8.34975 14.164C11.5581 14.2099 14.3722 14.164 17.6997 13.9074C18.2291 13.8172 18.7183 13.5678 19.1022 13.1924C19.3589 12.9356 19.5506 12.6214 19.6614 12.2757C19.9892 11.2698 20.1503 10.217 20.1381 9.15904C20.1747 8.64571 20.1747 5.54738 20.1747 4.99738ZM8.01975 9.70904V4.03488L13.4464 6.88571C11.9247 7.72904 9.91725 8.68238 8.01975 9.70904Z" fill="#64676A"/> </svg>',
}
const globePath = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"> <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <path d="M2 12H22" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <path d="M12 2C14.5013 4.73835 15.9228 8.29203 16 12C15.9228 15.708 14.5013 19.2616 12 22C9.49872 19.2616 8.07725 15.708 8 12C8.07725 8.29203 9.49872 4.73835 12 2V2Z" stroke="#64676A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> </svg>'
const socials = computed(() => {
  const rec = ex.value?.social || {}
  return SOCIAL_ORDER.filter(k => rec[k]).map(k => [k, rec[k]] as [string, string])
})

const mapsUrl = computed(() => {
  const loc = ex.value?.location
  if (loc?.url) return loc.url
  if (loc?.address) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(loc.address)}`
  return null
})
const mapEmbedSrc = computed(() => {
  const addr = ex.value?.location?.address
  return addr ? `https://maps.google.com/maps?q=${encodeURIComponent(addr)}&output=embed` : null
})

function ctaHref(v: string) {
  return /^https?:\/\//i.test(v) ? v : `https://${v}`
}

// Inline video playback for the "Videos" section (built from the spotlight —
// the API doesn't yet expose a dedicated video gallery for booths).
const videoPlaying = ref(false)

// ── Right rail ──
// Driven by the booth's CTA entries (Exhibitor admin › Details › CTA): a
// "text" CTA becomes the promo card, "image" CTAs become the image cards,
// "video" CTAs become the video card. Booths with no CTA of a given type
// fall back to the about copy / project & product shots / spotlight video
// shown in the main column, so the rail is never empty.
const railTextCta = computed(() => ex.value?.cta.find(c => c.type === 'text' && c.description))
const railTextExpanded = ref(false)
const railText = computed(() => {
  const c = railTextCta.value
  if (c) return { html: c.description, buttonLabel: c.button_label, buttonLink: c.button_link }
  if (ex.value?.about) return { html: ex.value.about, buttonLabel: '', buttonLink: '' }
  return null
})

const railImageCtas = computed(() => ex.value?.cta.filter(c => c.type === 'image' && c.image_url) || [])
const railImages = computed(() => {
  if (railImageCtas.value.length) {
    return railImageCtas.value.map(c => ({ src: c.image_url, href: c.button_link || null }))
  }
  return [...ex.value?.projects || [], ...ex.value?.products || []]
    .map(p => p.image_url)
    .filter((u): u is string => !!u)
    .slice(0, 2)
    .map(src => ({ src, href: null as string | null }))
})

// YouTube exposes a real preview frame at a predictable thumbnail URL — no API
// call needed. Other platforms (Vimeo/Facebook/Other) have no such public,
// key-less thumbnail endpoint, so those fall back to a plain dark placeholder
// rather than the exhibitor's logo (which isn't a video preview at all).
function youtubeThumb(url: string): string | null {
  const m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{11})/)
  return m ? `https://img.youtube.com/vi/${m[1]}/hqdefault.jpg` : null
}

const railVideo = computed(() => {
  const c = ex.value?.cta.find(c => c.type === 'video' && c.videos.length)
  const v = c?.videos[0]
  if (v) return { url: v.url, external: true, thumb: v.platform?.toLowerCase() === 'youtube' ? youtubeThumb(v.url) : null }
  if (ex.value?.spotlight.type === 'video' && ex.value.spotlight.url) return { url: ex.value.spotlight.url, external: false, thumb: null }
  return null
})
const railVideoPlaying = ref(false)

const contactSec = ref<HTMLElement | null>(null)
function scrollToContact() {
  contactSec.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

// Member cards show a bookmark affordance in the reference design, but
// members have no stable id in the API today, so this is a display-only,
// per-visit toggle rather than a persisted bookmark.
const memberSaved = ref<boolean[]>([])
function toggleMemberSaved(i: number) {
  memberSaved.value[i] = !memberSaved.value[i]
}
</script>

<template>
  <div class="page">
    <div v-if="store.detailLoading && !ex" class="state">Loading exhibitor…</div>
    <div v-else-if="store.detailError || !ex" class="state">
      Couldn’t load this exhibitor.
      <NuxtLink to="/exhibitors" class="link">Back to exhibitors</NuxtLink>
    </div>

    <div v-else class="layout">
    <div class="panel">
      <header class="panel-head">
        <h1>Exhibitor Info</h1>
        <button class="x" type="button" aria-label="Close" @click="router.back()">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>
      </header>

      <div class="banner">
        <video v-if="ex.spotlight.type === 'video' && ex.spotlight.url" :src="ex.spotlight.url" controls playsinline />
        <img v-else-if="ex.spotlight.url" :src="ex.spotlight.url" :alt="ex.name">
        <div v-else class="banner-fallback" />
      </div>

      <div class="idrow">
        <div class="logo"><AppImage :src="ex.logo_url" :alt="ex.name" /></div>
        <div class="idinfo">
          <h2 class="title">{{ ex.name }}</h2>
          <p class="submeta">
            <span v-if="ex.booth">Stall : {{ ex.booth }}</span>
            <span>Type : {{ ex.type === 'sponsor' ? 'Sponsor' : 'Exhibitor' }}</span>
          </p>
        </div>
        <div v-if="ex.can_rate && auth.isAuthed" class="stars" title="Rate this exhibitor" @mouseleave="hoverRating = 0">
          <button v-for="n in 5" :key="n" type="button" class="star" :class="{ on: (hoverRating || rating) >= n }"
            :title="`Rate ${n} / 5`" :aria-label="`Rate ${n} out of 5`" :disabled="ratingSaving"
            @mouseenter="hoverRating = n" @click="setRating(n)">
            <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z" /></svg>
          </button>
        </div>
        <div v-else class="stars">
          <svg v-for="n in 5" :key="n" :class="{ on: n <= rating }" viewBox="0 0 24 24">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z" />
          </svg>
        </div>
      </div>

      <div class="actionsrow">
        <button class="sq" type="button" :class="{ on: bookmarked }" :title="bookmarked ? 'Saved' : 'Save'"
          @click="toggleBookmark">
          <svg xmlns="http://www.w3.org/2000/svg" width="14" height="20" viewBox="0 0 14 20" fill="none">
            <path d="M11 0H3.00001C2.20436 0 1.4413 0.316071 0.878688 0.87868C0.316078 1.44129 7.88292e-06 2.20435 7.88292e-06 3V19C-0.000691684 19.1762 0.0451825 19.3495 0.132986 19.5023C0.220789 19.655 0.347404 19.7819 0.500008 19.87C0.652027 19.9578 0.824471 20.004 1.00001 20.004C1.17554 20.004 1.34799 19.9578 1.50001 19.87L7.00001 16.69L12.5 19.87C12.6524 19.9564 12.8248 20.0012 13 20C13.1752 20.0012 13.3476 19.9564 13.5 19.87C13.6526 19.7819 13.7792 19.655 13.867 19.5023C13.9548 19.3495 14.0007 19.1762 14 19V3C14 2.20435 13.6839 1.44129 13.1213 0.87868C12.5587 0.316071 11.7957 0 11 0ZM12 17.27L7.50001 14.67C7.34799 14.5822 7.17554 14.536 7.00001 14.536C6.82447 14.536 6.65203 14.5822 6.50001 14.67L2.00001 17.27V3C2.00001 2.73478 2.10536 2.48043 2.2929 2.29289C2.48044 2.10536 2.73479 2 3.00001 2H11C11.2652 2 11.5196 2.10536 11.7071 2.29289C11.8947 2.48043 12 2.73478 12 3V17.27Z" fill="#6452E7"/>
          </svg>
        </button>
        <button v-if="chatEnabled" class="pill" type="button" @click="openChat">
          <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17" fill="none">
            <path d="M12.5235 5.83333H4.19016C3.96914 5.83333 3.75718 5.92113 3.6009 6.07741C3.44462 6.23369 3.35682 6.44565 3.35682 6.66667C3.35682 6.88768 3.44462 7.09964 3.6009 7.25592C3.75718 7.4122 3.96914 7.5 4.19016 7.5H12.5235C12.7445 7.5 12.9565 7.4122 13.1127 7.25592C13.269 7.09964 13.3568 6.88768 13.3568 6.66667C13.3568 6.44565 13.269 6.23369 13.1127 6.07741C12.9565 5.92113 12.7445 5.83333 12.5235 5.83333ZM9.19016 9.16667H4.19016C3.96914 9.16667 3.75718 9.25446 3.6009 9.41074C3.44462 9.56702 3.35682 9.77899 3.35682 10C3.35682 10.221 3.44462 10.433 3.6009 10.5893C3.75718 10.7455 3.96914 10.8333 4.19016 10.8333H9.19016C9.41117 10.8333 9.62313 10.7455 9.77941 10.5893C9.93569 10.433 10.0235 10.221 10.0235 10C10.0235 9.77899 9.93569 9.56702 9.77941 9.41074C9.62313 9.25446 9.41117 9.16667 9.19016 9.16667ZM8.35682 0C7.26247 0 6.17884 0.215548 5.16779 0.634337C4.15675 1.05313 3.23809 1.66696 2.46427 2.44078C0.901462 4.00358 0.0234886 6.1232 0.0234886 8.33333C0.0162035 10.2576 0.682482 12.1238 1.90682 13.6083L0.240155 15.275C0.124524 15.3922 0.0461937 15.541 0.0150486 15.7027C-0.0160965 15.8643 0.00133908 16.0316 0.0651553 16.1833C0.13437 16.3333 0.246575 16.4593 0.387528 16.5453C0.52848 16.6314 0.691823 16.6736 0.856822 16.6667H8.35682C10.567 16.6667 12.6866 15.7887 14.2494 14.2259C15.8122 12.6631 16.6902 10.5435 16.6902 8.33333C16.6902 6.1232 15.8122 4.00358 14.2494 2.44078C12.6866 0.877974 10.567 0 8.35682 0V0ZM8.35682 15H2.86516L3.64016 14.225C3.79536 14.0689 3.88248 13.8577 3.88248 13.6375C3.88248 13.4173 3.79536 13.2061 3.64016 13.05C2.54898 11.96 1.86947 10.5254 1.7174 8.99066C1.56533 7.45587 1.95011 5.91584 2.80618 4.63294C3.66225 3.35003 4.93665 2.40363 6.41225 1.95498C7.88785 1.50632 9.47337 1.58317 10.8987 2.17243C12.324 2.76169 13.5009 3.8269 14.2289 5.18658C14.9568 6.54626 15.1909 8.11629 14.8911 9.62917C14.5913 11.1421 13.7762 12.5042 12.5848 13.4835C11.3933 14.4629 9.89912 14.9988 8.35682 15V15Z" fill="#6452E7"/>
          </svg>
          Chat
        </button>
        <button v-if="meetEnabled" class="pill" type="button" @click="openMeet">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
            <g clip-path="url(#clip0_3487_31341)">
              <path d="M19.1673 5.83301L13.334 9.99967L19.1673 14.1663V5.83301Z" stroke="#6452E7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M11.6673 4.16699H2.50065C1.58018 4.16699 0.833984 4.91318 0.833984 5.83366V14.167C0.833984 15.0875 1.58018 15.8337 2.50065 15.8337H11.6673C12.5878 15.8337 13.334 15.0875 13.334 14.167V5.83366C13.334 4.91318 12.5878 4.16699 11.6673 4.16699Z" stroke="#6452E7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </g>
            <defs>
              <clipPath id="clip0_3487_31341">
              <rect width="20" height="20" fill="white"/>
              </clipPath>
            </defs>
          </svg>
          Meet
        </button>
        <button class="sq" type="button" title="Share" @click="share">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="21" viewBox="0 0 20 21" fill="none">
            <path d="M16.0065 12.0323C15.4157 12.0362 14.833 12.171 14.3005 12.4269C13.768 12.6828 13.2987 13.0535 12.9265 13.5123L7.8265 11.1623C8.06629 10.4281 8.06629 9.63659 7.8265 8.90234L12.9265 6.55234C13.5281 7.27831 14.3668 7.76842 15.2946 7.93618C16.2225 8.10394 17.1797 7.93857 17.9974 7.46924C18.8152 6.99992 19.4409 6.25682 19.764 5.37108C20.0872 4.48535 20.0871 3.51392 19.7638 2.62825C19.4405 1.74257 18.8146 0.999584 17.9968 0.530409C17.179 0.0612344 16.2217 -0.103966 15.2939 0.0639647C14.3662 0.231895 13.5275 0.72216 12.926 1.44824C12.3245 2.17431 11.9989 3.08952 12.0065 4.03234C12.0095 4.27057 12.0329 4.50811 12.0765 4.74234L6.7965 7.17234C6.23358 6.62192 5.52074 6.24986 4.7473 6.10277C3.97386 5.95568 3.17422 6.04011 2.44855 6.34547C1.72288 6.65084 1.10346 7.16356 0.667898 7.8194C0.232336 8.47524 0 9.24504 0 10.0323C0 10.8196 0.232336 11.5894 0.667898 12.2453C1.10346 12.9011 1.72288 13.4138 2.44855 13.7192C3.17422 14.0246 3.97386 14.109 4.7473 13.9619C5.52074 13.8148 6.23358 13.4428 6.7965 12.8923L12.0765 15.3223C12.0329 15.5566 12.0095 15.7941 12.0065 16.0323C12.0065 16.8235 12.2411 17.5968 12.6806 18.2546C13.1201 18.9124 13.7449 19.4251 14.4758 19.7279C15.2067 20.0306 16.0109 20.1098 16.7869 19.9555C17.5628 19.8011 18.2755 19.4202 18.8349 18.8608C19.3943 18.3014 19.7753 17.5886 19.9296 16.8127C20.084 16.0368 20.0048 15.2325 19.702 14.5016C19.3993 13.7707 18.8866 13.146 18.2288 12.7065C17.571 12.2669 16.7976 12.0323 16.0065 12.0323ZM16.0065 2.03234C16.4021 2.03234 16.7887 2.14964 17.1176 2.3694C17.4465 2.58916 17.7029 2.90152 17.8543 3.26697C18.0056 3.63243 18.0452 4.03456 17.9681 4.42252C17.8909 4.81048 17.7004 5.16685 17.4207 5.44655C17.141 5.72626 16.7846 5.91674 16.3967 5.99391C16.0087 6.07108 15.6066 6.03148 15.2411 5.8801C14.8757 5.72872 14.5633 5.47238 14.3436 5.14348C14.1238 4.81458 14.0065 4.4279 14.0065 4.03234C14.0065 3.50191 14.2172 2.9932 14.5923 2.61813C14.9674 2.24305 15.4761 2.03234 16.0065 2.03234ZM4.0065 12.0323C3.61094 12.0323 3.22426 11.915 2.89536 11.6953C2.56646 11.4755 2.31011 11.1632 2.15874 10.7977C2.00736 10.4323 1.96776 10.0301 2.04493 9.64216C2.1221 9.2542 2.31258 8.89783 2.59228 8.61813C2.87199 8.33842 3.22836 8.14794 3.61632 8.07077C4.00428 7.9936 4.40641 8.03321 4.77186 8.18458C5.13732 8.33596 5.44967 8.5923 5.66944 8.9212C5.8892 9.2501 6.0065 9.63678 6.0065 10.0323C6.0065 10.5628 5.79578 11.0715 5.42071 11.4466C5.04564 11.8216 4.53693 12.0323 4.0065 12.0323ZM16.0065 18.0323C15.6109 18.0323 15.2243 17.915 14.8954 17.6953C14.5665 17.4755 14.3101 17.1632 14.1587 16.7977C14.0074 16.4323 13.9678 16.0301 14.0449 15.6422C14.1221 15.2542 14.3126 14.8978 14.5923 14.6181C14.872 14.3384 15.2284 14.1479 15.6163 14.0708C16.0043 13.9936 16.4064 14.0332 16.7719 14.1846C17.1373 14.336 17.4497 14.5923 17.6694 14.9212C17.8892 15.2501 18.0065 15.6368 18.0065 16.0323C18.0065 16.5628 17.7958 17.0715 17.4207 17.4466C17.0456 17.8216 16.5369 18.0323 16.0065 18.0323Z" fill="#6452E7"/>
          </svg>
        </button>
      </div>
      <p v-if="copied" class="copied">Link copied</p>

      <hr class="rule">

      <section v-if="ex.about" class="sec">
        <h3>About</h3>
        <div class="rich" v-html="ex.about" />
      </section>

      <section v-if="ex.contact.phone || ex.contact.email" ref="contactSec" class="sec">
        <h3>Get in Touch</h3>
        <a v-if="ex.contact.phone" :href="`tel:${ex.contact.phone}`" class="touch">
          <svg viewBox="0 0 24 24">
            <path
              d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.6a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.4c.8.3 1.7.6 2.6.7A2 2 0 0 1 22 16.9z" />
          </svg>
          {{ ex.contact.phone }}
        </a>
        <a v-if="ex.contact.email" :href="`mailto:${ex.contact.email}`" class="touch">
          <svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="M22 6l-10 7L2 6" /></svg>
          {{ ex.contact.email }}
        </a>
      </section>

      <div v-if="socials.length || ex.website" class="socialrow">
        <div class="socials">
          <a v-for="[k, v] in socials" :key="k" :href="v" target="_blank" rel="noopener" class="ic" :title="k" v-html="socialIcons[k]">
            
          </a>
          <a v-if="ex.website" :href="ex.website" target="_blank" rel="noopener" class="ic" title="Website" v-html="globePath">
          </a>
        </div>
      </div>

      <hr class="rule">

      <section v-if="ex.spotlight.type === 'video' && ex.spotlight.url" class="sec">
        <h3>Videos (1)</h3>
        <div class="mediagrid">
          <div class="videocard" @click="videoPlaying = true">
            <video v-if="videoPlaying" :src="ex.spotlight.url" controls autoplay playsinline />
            <template v-else>
              <div class="videothumb" />
              <span class="play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg></span>
            </template>
          </div>
        </div>
      </section>

      <section v-if="ex.projects.length" class="sec">
        <h3>Projects ({{ ex.projects.length }})</h3>
        <div class="mediagrid">
          <article v-for="(p, i) in ex.projects" :key="i" class="mediacard">
            <AppImage :src="p.image_url" :alt="p.name" />
          </article>
        </div>
      </section>

      <section v-if="ex.products.length" class="sec">
        <h3>Products ({{ ex.products.length }})</h3>
        <div class="mediagrid">
          <article v-for="(p, i) in ex.products" :key="i" class="mediacard">
            <AppImage :src="p.image_url" :alt="p.name" />
          </article>
        </div>
      </section>

      <section v-if="ex.members.length" class="sec">
        <h3>Members ({{ ex.members.length }})</h3>
        <div class="membergrid">
          <article v-for="(m, i) in ex.members" :key="i" class="membercard">
            <div class="memphoto">
              <UserAvatar :src="m.avatar_url" :name="m.name" />
              <button class="membm" type="button" :class="{ on: memberSaved[i] }"
                :title="memberSaved[i] ? 'Saved' : 'Save'" @click="toggleMemberSaved(i)">
                <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
              </button>
            </div>
            <div class="memfoot">
              <strong>{{ m.name }}</strong>
              <span v-if="m.designation">{{ m.designation }}</span>
              <span v-if="m.company" class="memco">{{ m.company }}</span>
            </div>
          </article>
        </div>
      </section>

      <section v-if="mapEmbedSrc || mapsUrl" class="sec">
        <h3>Map</h3>
        <div class="mapbox">
          <iframe v-if="mapEmbedSrc" :src="mapEmbedSrc" loading="lazy" referrerpolicy="no-referrer-when-downgrade" />
          <a v-else :href="mapsUrl || '#'" target="_blank" rel="noopener" class="mapfallback">
            <span class="map-pin"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg></span>
            <span class="map-open">Open in Maps</span>
          </a>
        </div>
        <p v-if="ex.location.address" class="map-addr">{{ ex.location.address }}</p>
      </section>

      <section v-if="ex.documents.length" class="sec">
        <h3>Brochure ({{ ex.documents.length }})</h3>
        <div class="docgrid">
          <div v-for="d in ex.documents" :key="d.id" class="doc">
            <span class="doc-ic"><svg viewBox="0 0 24 24"><path d="M14 3v5h5M14 3H6v18h12V8zM8 13h8M8 17h5" /></svg></span>
            <span class="doc-name">{{ d.title }}<small>{{ docKindLabel(d.url) }}</small></span>
            <button v-if="d.url" class="doc-act" :class="{ on: briefcase.hasUrl(d.url) }" type="button"
              :title="briefcase.hasUrl(d.url) ? 'In your briefcase' : 'Add to briefcase'"
              @click="briefcase.toggleDoc({ title: d.title, url: d.url, kind: docKind(d.url) })">
              <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
            </button>
            <a :href="d.url || '#'" target="_blank" rel="noopener" download class="doc-act" title="Download">
              <svg viewBox="0 0 24 24"><path d="M12 3v12M7 12l5 5 5-5M5 21h14" /></svg>
            </a>
          </div>
        </div>
      </section>
    </div>

    <aside class="rail">
      <button class="rail-share" type="button" @click="openShareDetails">
        Share your details with us
        <svg viewBox="0 0 24 24">
          <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
          <path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" />
        </svg>
      </button>

      <div v-if="railText" class="rail-card">
        <div class="rail-about" :class="{ clamp: !railTextExpanded }" v-html="railText.html" />
        <button class="rail-readmore" type="button" @click="railTextExpanded = !railTextExpanded">
          {{ railTextExpanded ? 'Read Less' : 'Read More' }}
        </button>
        <a v-if="railText.buttonLink" class="rail-moreinfo" :href="ctaHref(railText.buttonLink)" target="_blank" rel="noopener">
          {{ railText.buttonLabel || 'Get More Info' }}
        </a>
        <button v-else class="rail-moreinfo" type="button" @click="scrollToContact">
          {{ railText.buttonLabel || 'Get More Info' }}
        </button>
      </div>

      <div v-for="(img, i) in railImages" :key="i" class="rail-media">
        <component :is="img.href ? 'a' : 'div'" :href="img.href ? ctaHref(img.href) : undefined"
          :target="img.href ? '_blank' : undefined" :rel="img.href ? 'noopener' : undefined">
          <img :src="img.src" :alt="ex.name">
        </component>
      </div>

      <div v-if="railVideo" class="rail-media rail-video" @click="railVideo.external ? undefined : (railVideoPlaying = true)">
        <a v-if="railVideo.external" :href="ctaHref(railVideo.url)" target="_blank" rel="noopener" class="rail-video-link">
          <img v-if="railVideo.thumb" :src="railVideo.thumb" :alt="ex.name" class="rail-video-thumb">
          <div v-else class="rail-video-thumb-fallback" />
          <span class="rail-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg></span>
        </a>
        <template v-else>
          <video v-if="railVideoPlaying" :src="railVideo.url" controls autoplay playsinline />
          <template v-else>
            <div class="rail-video-thumb-fallback" />
            <span class="rail-play"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z" /></svg></span>
          </template>
        </template>
      </div>
    </aside>
    </div>
  </div>
</template>

<style scoped>
.page {
  max-width: 1180px;
  margin: 0 auto;
}

.layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 340px;
  gap: 20px;
  align-items: start;
}

@media (max-width: 900px) {
  .layout {
    grid-template-columns: 1fr;
  }
}

.state {
  background: #fff;
  border-radius: 14px;
  padding: 60px 0;
  text-align: center;
  color: #64748b;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.link {
  color: var(--brand-primary);
  font-weight: 600;
  margin-left: 6px;
}

/* ── Panel ── */
.panel {
  background: #fff;
  border-radius: 16px;
  overflow: hidden;
  box-shadow: 0 1px 2px rgba(15, 23, 42, .05);
}

.panel-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
}

.panel-head h1 {
  margin: 0;
  font-size: 22px;
  font-weight: 700;
  color: #212529;
}

.x {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 8px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: #64676A;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0;
}

.x:hover {
  background: #f1f5f9;
}

.x svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}

.banner {
  margin: 0 24px;
  height: 260px;
  border-radius: 12px;
  overflow: hidden;
  background: #0f172a;
}

.banner video,
.banner img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.banner-fallback {
  width: 100%;
  height: 100%;
  background: linear-gradient(120deg, color-mix(in srgb, var(--brand-primary) 70%, #000), var(--brand-primary));
}

.idrow {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px 24px 18px;
}

.logo {
  width: 80px;
  height: 80px;
  border-radius: 8px;
  overflow: hidden;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  flex: 0 0 auto;
}

.logo :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.idinfo {
  flex: 1;
  min-width: 0;
}

.stars {
  display: flex;
  gap: 6px;
  flex: 0 0 auto;
  align-self: flex-start;
  padding-top: 4px;
}

.stars svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: var(--brand-primary);
  stroke-width: 1.6;
  stroke-linejoin: round;
}

.stars svg.on {
  fill: var(--brand-primary);
}

.star {
  border: 0;
  background: transparent;
  padding: 0;
  color: #cbd5e1;
  cursor: pointer;
  transition: color .16s ease, transform .16s ease;
}

.star svg {
  display: block;
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linejoin: round;
}

.star:hover,
.star.on {
  color: var(--brand-primary);
}

.star.on svg {
  fill: currentColor;
}

.star:hover {
  transform: scale(1.06);
}

.star:focus-visible {
  outline: 2px solid color-mix(in srgb, var(--brand-primary) 45%, white);
  outline-offset: 3px;
  border-radius: 4px;
}

.star:disabled {
  cursor: default;
}

.title {
  margin: 0 0 8px;
  font-size: 20px;
  line-height: 1.2;
  font-weight: 800;
  color: #1e293b;
}

.submeta {
  margin: 0;
  display: flex;
  gap: 16px;
  line-height: 1.2;

  font-size: .88rem;
  color: #64748b;
}

.actionsrow {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 24px 6px;
}

.sq,
.pill {
  border: 1px solid var(--brand-primary);
  border-radius: 8px;
  background: #fff;
  color: var(--brand-primary);
  font: inherit;
  font-weight: 700;
  font-size: .92rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  max-height: 40px;
  transition: background .15s;
}

.sq:hover,
.pill:hover {
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.sq {
  width: 40px;
  height: 40px;
  flex: 0 0 auto;
}

.sq svg {
  width: 19px;
  height: 19px;
}

.pill {
  flex: 1;
  gap: 8px;
  padding: 13px 0;
}

.pill svg {
  width: 18px;
  height: 18px;
}

.copied {
  margin: 6px 24px 0;
  font-size: .8rem;
  color: var(--brand-primary);
}

.rule {
  margin: 22px 24px;
  border: none;
  border-top: 1px solid #eef0f3;
}

.sec {
  padding: 0 24px 22px;
}

.sec h3 {
  margin: 0 0 12px;
  font-size: 18px;
  font-weight: 700;
  color: #4D5154;
}

.rich {
  color: #4D5154;
  font-size: 14px;
  line-height: 1.65;
}

.rich :deep(p) {
  margin: 0 0 10px;
}

.touch {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 0;
  color: var(--brand-primary);
  text-decoration: none;
  font-size: .92rem;
}

.touch svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  flex: 0 0 auto;
}

/* Social row + share-details */
.socialrow {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
  padding: 0 24px 5px;
}

.socials {
  display: flex;
  gap: 10px;
}

.ic {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: #F7F7FB;
  color: #64748b;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ic:hover {
  color: var(--brand-primary);
  border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff);
}

.ic svg {
  width: 19px;
  height: 19px;
}

.sharedetails {
  border: none;
  border-radius: 10px;
  padding: 13px 20px;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  color: var(--brand-primary);
  font: inherit;
  font-weight: 700;
  font-size: .88rem;
  cursor: pointer;
}

.sharedetails:hover {
  background: color-mix(in srgb, var(--brand-primary) 20%, #fff);
}

/* Videos / Projects / Products */
.mediagrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 16px;
}

.mediacard {
  aspect-ratio: 1 / 1;
  border-radius: 12px;
  overflow: hidden;
  background: #eef1f8;
}

.mediacard :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.videocard {
  position: relative;
  aspect-ratio: 16 / 9;
  max-width: 460px;
  border-radius: 12px;
  overflow: hidden;
  background: #0f172a;
  cursor: pointer;
}

.videocard video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.videothumb {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #1e293b, #0f172a);
}

.play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.play svg {
  width: 52px;
  height: 52px;
  fill: #fff;
  background: rgba(255, 255, 255, .18);
  border-radius: 50%;
  padding: 12px;
  box-sizing: border-box;
}

/* Members */
.membergrid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: 16px;
}

.membercard {
  border: 1px solid #eef0f3;
  border-radius: 12px;
  overflow: hidden;
}

.memphoto {
  position: relative;
  aspect-ratio: 4 / 5;
  background: #eef1f8;
}

.memphoto :deep(img),
.memphoto :deep(svg) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.membm {
  position: absolute;
  top: 10px;
  right: 10px;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 9px;
  background: #fff;
  color: var(--brand-primary);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(15, 23, 42, .18);
}

.membm svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.membm.on {
  background: var(--brand-primary);
  color: #fff;
}

.membm.on svg {
  fill: currentColor;
}

.memfoot {
  padding: 10px 12px 12px;
  display: flex;
  flex-direction: column;
}

.memfoot strong {
  font-size: .88rem;
  color: #1e293b;
}

.memfoot span {
  font-size: .78rem;
  color: #64748b;
}

.memfoot .memco {
  font-size: .74rem;
  color: #94a3b8;
}

/* Map */
.mapbox {
  height: 220px;
  border-radius: 12px;
  overflow: hidden;
  background: #eef1f8;
}

.mapbox iframe {
  width: 100%;
  height: 100%;
  border: 0;
}

.mapfallback {
  display: block;
  position: relative;
  height: 100%;
  background: linear-gradient(120deg, #dbeafe, #e0e7ff);
  text-decoration: none;
}

.map-pin {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.map-pin svg {
  width: 34px;
  height: 34px;
  fill: none;
  stroke: #e02d2d;
  stroke-width: 1.8;
}

.map-open {
  position: absolute;
  top: 10px;
  left: 10px;
  display: inline-flex;
  align-items: center;
  gap: 5px;
  background: #fff;
  color: var(--brand-primary);
  font-size: .8rem;
  font-weight: 700;
  padding: 6px 10px;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(15, 23, 42, .15);
}

.map-addr {
  margin: 10px 0 0;
  font-size: .82rem;
  color: #64748b;
}

/* Brochure */
.docgrid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

@media (max-width: 560px) {
  .docgrid {
    grid-template-columns: 1fr;
  }
}

.doc {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
}

.doc-ic {
  color: #dc2626;
  flex: 0 0 auto;
}

.doc-ic svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.doc-name {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  font-size: .86rem;
  font-weight: 600;
  color: #1e293b;
}

.doc-name small {
  font-weight: 500;
  color: #94a3b8;
  font-size: .72rem;
}

.doc-act {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #64748b;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  text-decoration: none;
}

.doc-act:hover {
  color: var(--brand-primary);
  border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff);
}

.doc-act.on {
  background: var(--brand-primary);
  color: #fff;
  border-color: var(--brand-primary);
}

.doc-act svg {
  width: 15px;
  height: 15px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

/* ── Right rail ── */
.rail {
  display: flex;
  flex-direction: column;
  gap: 16px;
  position: sticky;
  top: 92px;
}

.rail-share {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: none;
  border-radius: 8px;
  max-height: 48px;
  padding: 12px 24px;
  background: color-mix(in srgb, var(--brand-primary) 10%, #fff);
  color: var(--brand-primary);
  font: inherit;
  font-weight: 700;
  font-size: 16px;
  cursor: pointer;
  text-align: center;
}

.rail-share:hover {
  background: color-mix(in srgb, var(--brand-primary) 18%, #fff);
}

.rail-share svg {
  width: 20px;
  height: 20px;
  flex: 0 0 auto;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.rail-card {
  background: #fff;
  border-radius: 12px;
  padding: 24px;
  border: 1px solid #E8E8EE
}

.rail-about {
  color: #475569;
  font-size: .95rem;
  line-height: 1.65;
}

.rail-about :deep(p) {
  margin: 0 0 8px;
}

.rail-about.clamp {
  display: -webkit-box;
  -webkit-line-clamp: 4;
  line-clamp: 4;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.rail-readmore {
  display: block;
  margin-top: 4px;
  border: none;
  background: none;
  color: var(--brand-primary);
  font: inherit;
  font-size: 14px;
  font-weight: 400;
  cursor: pointer;
  padding: 0;
}

.rail-moreinfo {
  display: block;
  width: 100%;
  margin-top: 12px;
  border: 1px solid var(--brand-primary);
  border-radius: 8px;
  padding: 6px 15px;
  max-height: 40px;
  background: #fff;
  color: var(--brand-primary);
  font: inherit;
  font-weight: 700;
  font-size: 14px;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  transition: background .15s;
}

.rail-moreinfo:hover {
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.rail-media {
  border-radius: 12px;
  overflow: hidden;
  aspect-ratio: 16 / 10;
  background: #0f172a;
}

.rail-media img,
.rail-media video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.rail-media > a,
.rail-media > div {
  display: block;
  width: 100%;
  height: 100%;
}

.rail-video {
  position: relative;
  cursor: pointer;
}

.rail-video-link {
  position: relative;
  display: block;
  width: 100%;
  height: 100%;
}

.rail-video-thumb-fallback {
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #1e293b, #0f172a);
}

.rail-play {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.rail-play svg {
  width: 40px;
  height: 40px;
  fill: #fff;
  background: #000000B2;
  border-radius: 50%;
  padding: 11px;
  box-sizing: border-box;
}

@media (max-width: 900px) {
  .rail {
    position: static;
  }
}
</style>

<script setup lang="ts">
import { briefcaseKind } from '~/stores/briefcase'

definePageMeta({ layout: 'event', middleware: 'auth' })

const route = useRoute()
const router = useRouter()
const store = useExhibitorsStore()
const contact = useExhibitorContactStore()
const bookmarks = useBookmarksStore()
const briefcase = useBriefcaseStore()

function docKind(url?: string | null) {
  return url ? briefcaseKind(url) : 'file'
}
function docKindLabel(url?: string | null) {
  return ({ pdf: 'PDF FILE', doc: 'DOC FILE', excel: 'EXCEL FILE', image: 'IMAGE' } as Record<string, string>)[docKind(url)] || 'FILE'
}

const id = computed(() => route.params.id as string)
const ex = computed(() => store.detail)

onMounted(() => {
  store.fetchDetail(id.value)
  bookmarks.fetch()
})
watch(id, v => v && store.fetchDetail(v))

const bookmarked = computed(() => bookmarks.isOn('exhibitor', id.value))

function openChat() {
  if (ex.value) contact.openFor({ id: ex.value.id, name: ex.value.name }, 'chat')
}
function openMeet() {
  if (ex.value) contact.openFor({ id: ex.value.id, name: ex.value.name }, 'meet')
}
function openShareDetails() {
  if (ex.value) contact.openFor({ id: ex.value.id, name: ex.value.name }, 'chat')
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
  facebook: 'M14 9h3V5h-3a4 4 0 0 0-4 4v2H7v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1z',
  instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6M17 6.5h.01',
  whatsapp: 'M20.5 3.5A10.5 10.5 0 0 0 3.8 16.7L2 22l5.5-1.7A10.5 10.5 0 1 0 20.5 3.5zM12 20a8 8 0 0 1-4.1-1.1l-.3-.2-3 .9.9-2.9-.2-.3A8 8 0 1 1 12 20zm4.4-6c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.5.1l-.7.9c-.1.2-.3.2-.5.1-1.5-.7-2.5-1.7-3.2-3-.1-.2 0-.4.1-.5l.5-.6c.1-.2.1-.4 0-.5l-.6-1.5c-.1-.3-.3-.3-.5-.3h-.5c-.2 0-.4.1-.5.3-.2.2-.7.8-.7 1.9s.7 2.2 1.6 3.3c1.6 1.9 2.8 2.5 4.5 3 .4.1.7.1 1-.1.3-.1 1-.4 1.1-.9.1-.4.1-.7.1-.8-.1-.1-.2-.2-.4-.3z',
  twitter: 'M18.9 3H21l-4.6 5.3L21.7 21h-6.1l-4.8-6.3L5.3 21H3.2l5-5.7L2.6 3h6.2l4.4 5.8zM17 19.3h1.7L8.2 4.6H6.4z',
  linkedin: 'M4 4h4v16H4zM6 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4M10 8h4v2a4 4 0 0 1 6 3v7h-4v-6a2 2 0 0 0-4 0v6h-4z',
  youtube: 'M22 12a20 20 0 0 0-.5-4.5 3 3 0 0 0-2.1-2C17.6 5 12 5 12 5s-5.6 0-7.4.5a3 3 0 0 0-2.1 2A20 20 0 0 0 2 12a20 20 0 0 0 .5 4.5 3 3 0 0 0 2.1 2C6.4 19 12 19 12 19s5.6 0 7.4-.5a3 3 0 0 0 2.1-2A20 20 0 0 0 22 12zM10 15V9l5 3z',
}
const globePath = 'M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20zM12 2a10 10 0 1 0 0 20 10 10 0 1 0 0-20z'
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

// A CTA "read more" toggle for long rich text.
const ctaExpanded = ref(false)
function ctaHref(v: string) {
  return /^https?:\/\//i.test(v) ? v : `https://${v}`
}

// Inline video playback for the "Videos" section (built from the spotlight —
// the API doesn't yet expose a dedicated video gallery for booths).
const videoPlaying = ref(false)

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

    <div v-else class="panel">
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
        <div class="stars" :title="ex.can_rate ? 'Rate this exhibitor' : ''">
          <svg v-for="n in 5" :key="n" viewBox="0 0 24 24">
            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z" />
          </svg>
        </div>
      </div>

      <h2 class="title">{{ ex.name }}</h2>
      <p class="submeta">
        <span v-if="ex.booth">Stall : {{ ex.booth }}</span>
        <span>Type : {{ ex.type === 'sponsor' ? 'Sponsor' : 'Exhibitor' }}</span>
      </p>

      <div class="actionsrow">
        <button class="sq" type="button" :class="{ on: bookmarked }" :title="bookmarked ? 'Saved' : 'Save'"
          @click="bookmarks.toggle('exhibitor', id)">
          <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
        </button>
        <button class="pill" type="button" @click="openChat">
          <svg viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></svg>
          Chat
        </button>
        <button class="pill" type="button" @click="openMeet">
          <svg viewBox="0 0 24 24"><path d="M23 7l-7 5 7 5V7z" /><rect x="1" y="5" width="15" height="14" rx="2" /></svg>
          Meet
        </button>
        <button class="sq" type="button" title="Share" @click="share">
          <svg viewBox="0 0 24 24">
            <circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" />
            <path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" />
          </svg>
        </button>
      </div>
      <p v-if="copied" class="copied">Link copied</p>

      <hr class="rule">

      <section v-if="ex.about" class="sec">
        <h3>About</h3>
        <div class="rich" v-html="ex.about" />
      </section>

      <section v-if="ex.contact.phone || ex.contact.email" class="sec">
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
          <a v-for="[k, v] in socials" :key="k" :href="v" target="_blank" rel="noopener" class="ic" :title="k">
            <svg viewBox="0 0 24 24"><path :d="socialIcons[k]" /></svg>
          </a>
          <a v-if="ex.website" :href="ex.website" target="_blank" rel="noopener" class="ic" title="Website">
            <svg viewBox="0 0 24 24"><path :d="globePath" /></svg>
          </a>
        </div>
        <button class="sharedetails" type="button" @click="openShareDetails">Share your details with us</button>
      </div>

      <section v-if="ex.cta.length" class="sec">
        <h3>More</h3>
        <template v-for="(c, i) in ex.cta" :key="i">
          <div v-if="c.type === 'TEXT' || (!c.type && c.value)" class="cta-text" :class="{ clamp: !ctaExpanded }">
            {{ c.value || c.label }}
          </div>
          <a v-else-if="c.type === 'LINK'" :href="ctaHref(c.value)" target="_blank" rel="noopener" class="cta-link">{{ c.label || c.value }}</a>
          <a v-else-if="c.type === 'BUTTON'" :href="ctaHref(c.value)" target="_blank" rel="noopener" class="cta-btn">{{ c.label || 'Button' }}</a>
        </template>
        <button v-if="ex.cta.some(c => (c.type === 'TEXT' || !c.type) && (c.value || '').length > 140)"
          class="readmore" type="button" @click="ctaExpanded = !ctaExpanded">
          {{ ctaExpanded ? '– READ LESS' : '+ READ MORE' }}
        </button>
      </section>

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
  </div>
</template>

<style scoped>
.page {
  max-width: 800px;
  margin: 0 auto;
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
  font-size: 1.15rem;
  font-weight: 800;
  color: #1e293b;
}

.x {
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: transparent;
  color: #1e293b;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.x:hover {
  background: #f1f5f9;
}

.x svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
}

.banner {
  margin: 0 24px;
  height: 300px;
  border-radius: 14px;
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
  align-items: flex-start;
  justify-content: space-between;
  padding: 20px 24px 0;
}

.logo {
  width: 88px;
  height: 88px;
  border-radius: 14px;
  overflow: hidden;
  background: color-mix(in srgb, var(--brand-primary) 12%, #fff);
  flex: 0 0 auto;
}

.logo :deep(img) {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.stars {
  display: flex;
  gap: 4px;
  padding-top: 6px;
}

.stars svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: var(--brand-primary);
  stroke-width: 1.6;
  stroke-linejoin: round;
}

.title {
  margin: 18px 24px 4px;
  font-size: 1.4rem;
  font-weight: 800;
  color: #1e293b;
}

.submeta {
  margin: 0 24px 18px;
  display: flex;
  gap: 16px;
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
  border-radius: 10px;
  background: #fff;
  color: var(--brand-primary);
  font: inherit;
  font-weight: 700;
  font-size: .92rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
}

.sq:hover,
.pill:hover {
  background: color-mix(in srgb, var(--brand-primary) 8%, #fff);
}

.sq {
  width: 48px;
  height: 48px;
  flex: 0 0 auto;
}

.sq svg {
  width: 19px;
  height: 19px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.9;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.pill {
  flex: 1;
  gap: 8px;
  padding: 13px 0;
}

.pill svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
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
  font-size: 1.02rem;
  font-weight: 800;
  color: #1e293b;
}

.rich {
  color: #475569;
  font-size: .92rem;
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
  padding: 0 24px 22px;
}

.socials {
  display: flex;
  gap: 10px;
}

.ic {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  background: #fff;
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
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
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

/* CTA */
.cta-text {
  color: #475569;
  font-size: .9rem;
  line-height: 1.6;
  white-space: pre-wrap;
}

.cta-text.clamp {
  display: -webkit-box;
  -webkit-line-clamp: 5;
  line-clamp: 5;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.cta-link {
  display: inline-block;
  margin-top: 8px;
  color: var(--brand-primary);
  font-weight: 600;
  font-size: .9rem;
}

.cta-btn {
  display: block;
  margin-top: 12px;
  text-align: center;
  background: var(--brand-primary);
  color: #fff;
  border-radius: 10px;
  padding: 12px;
  font-weight: 700;
  font-size: .9rem;
  text-decoration: none;
}

.readmore {
  display: block;
  margin-top: 10px;
  border: none;
  background: none;
  color: var(--brand-primary);
  font: inherit;
  font-size: .82rem;
  font-weight: 700;
  cursor: pointer;
  padding: 0;
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
</style>

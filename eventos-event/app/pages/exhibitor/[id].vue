<script setup lang="ts">
import { briefcaseKind } from '~/stores/briefcase'

definePageMeta({ layout: 'event', middleware: 'auth' })

const route = useRoute()
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

function openContact() {
  if (ex.value) contact.openFor({ id: ex.value.id, name: ex.value.name })
}

const copied = ref(false)
async function share() {
  const url = window.location.href
  try {
    if (navigator.share) await navigator.share({ title: ex.value?.name, url })
    else { await navigator.clipboard.writeText(url); copied.value = true; setTimeout(() => (copied.value = false), 1500) }
  } catch { /* dismissed */ }
}

const mapsUrl = computed(() => {
  const loc = ex.value?.location
  if (loc?.url) return loc.url
  if (loc?.address) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(loc.address)}`
  return null
})

// A CTA "read more" toggle for long rich text.
const ctaExpanded = ref(false)
function ctaHref(v: string) {
  return /^https?:\/\//i.test(v) ? v : `https://${v}`
}
</script>

<template>
  <div>
    <div v-if="store.detailLoading && !ex" class="state">Loading exhibitor…</div>
    <div v-else-if="store.detailError || !ex" class="state">
      Couldn’t load this exhibitor.
      <NuxtLink to="/exhibitors" class="link">Back to exhibitors</NuxtLink>
    </div>

    <template v-else>
      <!-- ── Banner ── -->
      <section class="hero">
        <button class="close" type="button" aria-label="Close" @click="$router.back()">
          <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
        </button>

        <div class="banner">
          <video v-if="ex.spotlight.type === 'video' && ex.spotlight.url" :src="ex.spotlight.url" controls playsinline />
          <img v-else-if="ex.spotlight.url" :src="ex.spotlight.url" :alt="ex.name">
          <div v-else class="banner-fallback" />
        </div>

        <div class="idbar">
          <div class="logo">
            <AppImage :src="ex.logo_url" :alt="ex.name" />
          </div>
          <h1 class="title">{{ ex.name }}</h1>

          <div class="idbar-right">
            <div class="stars" :title="ex.can_rate ? 'Rate this exhibitor' : ''">
              <svg v-for="n in 5" :key="n" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.27 5.82 21 7 14.14l-5-4.87 6.91-1.01L12 2z" /></svg>
            </div>
            <button class="round" type="button" title="Share" @click="share">
              <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" /></svg>
            </button>
          </div>
        </div>
      </section>

      <div class="grid">
        <!-- ── Left column ── -->
        <div class="col">
          <section v-if="ex.about" class="card">
            <h2>About</h2>
            <div class="rich" v-html="ex.about" />
          </section>

          <section v-if="ex.projects.length" class="card">
            <h2>Projects ({{ ex.projects.length }})</h2>
            <div class="proj-grid">
              <article v-for="(p, i) in ex.projects" :key="i" class="proj">
                <div class="proj-img">
                  <AppImage :src="p.image_url" :alt="p.name" />
                </div>
                <div v-if="p.name || p.description" class="proj-body">
                  <strong>{{ p.name }}</strong>
                  <p v-if="p.description">{{ p.description }}</p>
                </div>
              </article>
            </div>
          </section>

          <section v-if="ex.members.length" class="card">
            <h2>Members ({{ ex.members.length }})</h2>
            <div class="mem-grid">
              <article v-for="(m, i) in ex.members" :key="i" class="mem">
                <div class="mem-photo">
                  <UserAvatar :src="m.avatar_url" :name="m.name" />
                  <div class="mem-cap">
                    <strong>{{ m.name }}</strong>
                    <span v-if="m.designation">{{ m.designation }}</span>
                  </div>
                </div>
              </article>
            </div>
          </section>
        </div>

        <!-- ── Right column ── -->
        <div class="col">
          <button class="share-cta" type="button" @click="openContact">
            SHARE YOUR DETAILS
            <svg viewBox="0 0 24 24"><circle cx="18" cy="5" r="3" /><circle cx="6" cy="12" r="3" /><circle cx="18" cy="19" r="3" /><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" /></svg>
          </button>

          <section v-if="ex.contact.phone || ex.contact.email || ex.website" class="card">
            <h2>Get in touch</h2>
            <a v-if="ex.contact.phone" :href="`tel:${ex.contact.phone}`" class="touch">
              <span class="ic"><svg viewBox="0 0 24 24"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2 4.2 2 2 0 0 1 4 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.6a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.4c.8.3 1.7.6 2.6.7A2 2 0 0 1 22 16.9z" /></svg></span>
            {{ ex.contact.phone }}
            </a>
            <a v-if="ex.contact.email" :href="`mailto:${ex.contact.email}`" class="touch">
              <span class="ic"><svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2" /><path d="M22 6l-10 7L2 6" /></svg></span>
              {{ ex.contact.email }}
            </a>
            <a v-if="ex.website" :href="ex.website" target="_blank" rel="noopener" class="touch">
              <span class="ic"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" /><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z" /></svg></span>
              Visit website
            </a>
          </section>

          <section v-if="ex.cta.length" class="card">
            <h2>CTA</h2>
            <template v-for="(c, i) in ex.cta" :key="i">
              <div v-if="c.type === 'TEXT' || (!c.type && c.value)" class="cta-text" :class="{ clamp: !ctaExpanded }">{{ c.value || c.label }}</div>
              <a v-else-if="c.type === 'LINK'" :href="ctaHref(c.value)" target="_blank" rel="noopener" class="cta-link">{{ c.label || c.value }}</a>
              <a v-else-if="c.type === 'BUTTON'" :href="ctaHref(c.value)" target="_blank" rel="noopener" class="cta-btn">{{ c.label || 'Button' }}</a>
            </template>
            <button v-if="ex.cta.some(c => (c.type === 'TEXT' || !c.type) && (c.value || '').length > 140)" class="readmore" type="button" @click="ctaExpanded = !ctaExpanded">
              {{ ctaExpanded ? '– READ LESS' : '+ READ MORE' }}
            </button>
          </section>

          <section v-if="mapsUrl" class="card">
            <h2>Map</h2>
            <a :href="mapsUrl" target="_blank" rel="noopener" class="map">
              <span class="map-pin"><svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></svg></span>
              <span class="map-open">Maps <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3" /></svg></span>
            </a>
            <p v-if="ex.location.address" class="map-addr">{{ ex.location.address }}</p>
          </section>

          <section v-if="ex.documents.length" class="card">
            <h2>Brochure ({{ ex.documents.length }})</h2>
            <div v-for="d in ex.documents" :key="d.id" class="doc">
              <span class="doc-ic"><svg viewBox="0 0 24 24"><path d="M14 3v5h5M14 3H6v18h12V8zM8 13h8M8 17h5" /></svg></span>
              <span class="doc-name">{{ d.title }}<small>{{ docKindLabel(d.url) }}</small></span>
              <button
                v-if="d.url"
                class="doc-act"
                :class="{ on: briefcase.hasUrl(d.url) }"
                type="button"
                :title="briefcase.hasUrl(d.url) ? 'In your briefcase' : 'Add to briefcase'"
                @click="briefcase.toggleDoc({ title: d.title, url: d.url, kind: docKind(d.url) })"
              >
                <svg viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2" /><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16" /></svg>
              </button>
              <a :href="d.url || '#'" target="_blank" rel="noopener" download class="doc-act" title="Download">
                <svg viewBox="0 0 24 24"><path d="M12 3v12M7 12l5 5 5-5M5 21h14" /></svg>
              </a>
            </div>
          </section>

          <button class="bookmark" type="button" :class="{ on: bookmarked }" @click="bookmarks.toggle('exhibitor', id)">
            <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
            {{ bookmarked ? 'Saved' : 'Save exhibitor' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.state { background: #fff; border-radius: 14px; padding: 60px 0; text-align: center; color: #64748b; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.link, .state .link { color: var(--brand-primary); font-weight: 600; margin-left: 6px; }

/* ── Hero ── */
.hero { position: relative; background: #fff; border-radius: 14px; overflow: hidden; box-shadow: 0 1px 2px rgba(15,23,42,.05); margin-bottom: 20px; }
.close { position: absolute; top: 12px; right: 12px; z-index: 3; width: 30px; height: 30px; border: none; border-radius: 50%; background: #e02d2d; color: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.close svg { width: 15px; height: 15px; fill: none; stroke: currentColor; stroke-width: 2.4; stroke-linecap: round; }

.banner { height: 340px; background: #0f172a; }
.banner video, .banner img { width: 100%; height: 100%; object-fit: cover; }
.banner-fallback { width: 100%; height: 100%; background: linear-gradient(120deg, color-mix(in srgb, var(--brand-primary) 70%, #000), var(--brand-primary)); }

.idbar { display: flex; align-items: center; gap: 18px; padding: 0 26px 20px; position: relative; }
.logo { width: 110px; height: 84px; margin-top: -40px; background: #fff; border-radius: 12px; box-shadow: 0 6px 18px rgba(15,23,42,.15); display: flex; align-items: center; justify-content: center; padding: 10px; flex: 0 0 auto; }
.logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.logo .ini { font-size: 1.8rem; font-weight: 800; color: var(--brand-primary); }
.title { margin: 0; font-size: 1.35rem; font-weight: 800; color: #1e293b; flex: 1; }
.idbar-right { display: flex; align-items: center; gap: 16px; }
.stars { display: flex; gap: 4px; }
.stars svg { width: 22px; height: 22px; fill: none; stroke: var(--brand-primary); stroke-width: 1.6; stroke-linejoin: round; }
.round { width: 38px; height: 38px; border-radius: 50%; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.round:hover { color: var(--brand-primary); border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff); }
.round svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

/* ── Grid ── */
.grid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 20px; align-items: start; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
.col { display: flex; flex-direction: column; gap: 20px; }

.card { background: #fff; border-radius: 14px; padding: 20px 22px; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.card h2 { margin: 0 0 14px; font-size: 1rem; font-weight: 800; color: #334155; }
.rich { color: #475569; font-size: .92rem; line-height: 1.65; }
.rich :deep(p) { margin: 0 0 10px; }

/* Projects */
.proj-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
.proj { border-radius: 12px; overflow: hidden; }
.proj-img { aspect-ratio: 1 / 1; background: #8a6d5a; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; text-align: center; padding: 12px; }
.proj-img img { width: 100%; height: 100%; object-fit: cover; }
.proj-body { padding: 8px 2px 0; }
.proj-body strong { font-size: .9rem; color: #1e293b; }
.proj-body p { margin: 3px 0 0; font-size: .82rem; color: #64748b; }

/* Members */
.mem-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px; }
.mem-photo { position: relative; aspect-ratio: 4 / 3; border-radius: 12px; overflow: hidden; background: #eef1f8; display: flex; align-items: center; justify-content: center; }
.mem-photo img { width: 100%; height: 100%; object-fit: cover; }
.mem-photo .ini { font-size: 2rem; font-weight: 800; color: #94a3b8; }
.mem-cap { position: absolute; left: 0; right: 0; bottom: 0; background: linear-gradient(transparent, rgba(15,23,42,.85)); color: #fff; padding: 20px 12px 10px; display: flex; flex-direction: column; }
.mem-cap strong { font-size: .86rem; }
.mem-cap span { font-size: .74rem; opacity: .85; }

/* Right column */
.share-cta { display: flex; align-items: center; justify-content: space-between; gap: 10px; width: 100%; border: none; border-radius: 14px; padding: 16px 20px; background: color-mix(in srgb, var(--brand-primary) 55%, #fff); color: #fff; font: inherit; font-size: .9rem; font-weight: 800; letter-spacing: .3px; cursor: pointer; box-shadow: 0 1px 2px rgba(15,23,42,.05); }
.share-cta:hover { background: var(--brand-primary); }
.share-cta svg { width: 17px; height: 17px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.touch { display: flex; align-items: center; gap: 12px; padding: 8px 0; color: #334155; text-decoration: none; font-size: .9rem; }
.touch + .touch { border-top: 1px solid #f1f2f6; }
.touch:hover { color: var(--brand-primary); }
.ic { width: 34px; height: 34px; border-radius: 50%; background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
.ic svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.cta-text { color: #475569; font-size: .9rem; line-height: 1.6; white-space: pre-wrap; }
.cta-text.clamp { display: -webkit-box; -webkit-line-clamp: 5; line-clamp: 5; -webkit-box-orient: vertical; overflow: hidden; }
.cta-link { display: inline-block; margin-top: 8px; color: var(--brand-primary); font-weight: 600; font-size: .9rem; }
.cta-btn { display: block; margin-top: 12px; text-align: center; background: var(--brand-primary); color: #fff; border-radius: 10px; padding: 12px; font-weight: 700; font-size: .9rem; text-decoration: none; }
.readmore { display: block; margin-top: 10px; border: none; background: none; color: var(--brand-primary); font: inherit; font-size: .82rem; font-weight: 700; cursor: pointer; padding: 0; }

/* Map */
.map { display: block; position: relative; height: 150px; border-radius: 10px; overflow: hidden; background: linear-gradient(120deg, #dbeafe, #e0e7ff); text-decoration: none; }
.map-pin { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; }
.map-pin svg { width: 34px; height: 34px; fill: none; stroke: #e02d2d; stroke-width: 1.8; }
.map-open { position: absolute; top: 10px; left: 10px; display: inline-flex; align-items: center; gap: 5px; background: #fff; color: var(--brand-primary); font-size: .8rem; font-weight: 700; padding: 6px 10px; border-radius: 8px; box-shadow: 0 1px 3px rgba(15,23,42,.15); }
.map-open svg { width: 13px; height: 13px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.map-addr { margin: 10px 0 0; font-size: .82rem; color: #64748b; }

/* Brochure */
.doc { display: flex; align-items: center; gap: 12px; padding: 10px 0; text-decoration: none; color: #334155; }
.doc + .doc { border-top: 1px solid #f1f2f6; }
.doc-ic { color: #dc2626; flex: 0 0 auto; }
.doc-ic svg { width: 26px; height: 26px; fill: none; stroke: currentColor; stroke-width: 1.6; stroke-linecap: round; stroke-linejoin: round; }
.doc-name { flex: 1; min-width: 0; display: flex; flex-direction: column; font-size: .86rem; font-weight: 600; color: var(--brand-primary); }
.doc-name small { font-weight: 500; color: #94a3b8; font-size: .72rem; }
.doc-act { width: 34px; height: 34px; border-radius: 8px; border: 1px solid #e2e8f0; background: #fff; color: #64748b; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; text-decoration: none; }
.doc-act:hover { color: var(--brand-primary); border-color: color-mix(in srgb, var(--brand-primary) 40%, #fff); }
.doc-act.on { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
.doc-act svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

.bookmark { display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; background: #fff; color: #475569; font: inherit; font-size: .9rem; font-weight: 700; cursor: pointer; }
.bookmark svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.bookmark.on { background: var(--brand-primary); color: #fff; border-color: var(--brand-primary); }
.bookmark.on svg { fill: currentColor; }
</style>

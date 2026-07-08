<script setup lang="ts">
import type { Exhibitor } from '~/stores/exhibitors'

const props = defineProps<{ exhibitor: Exhibitor }>()
const store = useExhibitorsStore()

const bookmarks = useBookmarksStore()
const bookmarked = computed(() => bookmarks.isOn('exhibitor', props.exhibitor.id))

function initials(name?: string | null) {
  const p = (name || '?').trim().split(/\s+/)
  return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase() || '?'
}

function money(v: number | null) {
  if (v === null) return null
  return new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(v)
}

const socialIcons: Record<string, string> = {
  linkedin: 'M4 4h4v16H4zM6 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4M10 8h4v2a4 4 0 0 1 6 3v7h-4v-6a2 2 0 0 0-4 0v6h-4z',
  twitter: 'M22 5a8 8 0 0 1-2.3.6A4 4 0 0 0 21.4 3a8 8 0 0 1-2.5 1A4 4 0 0 0 12 7.5a11 11 0 0 1-8-4 4 4 0 0 0 1.2 5.3A4 4 0 0 1 3 8.3a4 4 0 0 0 3.2 4 4 4 0 0 1-1.8.1 4 4 0 0 0 3.7 2.8A8 8 0 0 1 2 18a11 11 0 0 0 18-8.5A6 6 0 0 0 22 5z',
  facebook: 'M14 9h3V5h-3a4 4 0 0 0-4 4v2H7v4h3v6h4v-6h3l1-4h-4V9a1 1 0 0 1 1-1z',
  instagram: 'M4 8a4 4 0 0 1 4-4h8a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H8a4 4 0 0 1-4-4zM12 9a3 3 0 1 0 0 6 3 3 0 0 0 0-6M17 6.5h.01',
}
const socials = computed(() => Object.entries(props.exhibitor.social || {}).filter(([, v]) => v))
</script>

<template>
  <div class="overlay" @click.self="store.close()">
    <div class="modal" role="dialog" aria-modal="true">
      <button class="x" type="button" aria-label="Close" @click="store.close()">
        <svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>

      <header class="head">
        <div class="logo">
          <img v-if="exhibitor.logo_url" :src="exhibitor.logo_url" :alt="exhibitor.name">
          <span v-else class="ini">{{ initials(exhibitor.name) }}</span>
        </div>
        <div class="ident">
          <div class="tags">
            <span class="tag" :class="exhibitor.type">{{ exhibitor.type === 'sponsor' ? 'Sponsor' : 'Exhibitor' }}</span>
            <span v-if="exhibitor.category" class="cat">{{ exhibitor.category }}</span>
          </div>
          <h2>{{ exhibitor.name }}</h2>
          <p v-if="exhibitor.booth" class="booth">
            <svg viewBox="0 0 24 24"><path d="M4 9l1-4h14l1 4M4 9v11h16V9M4 9h16M9 20v-6h6v6" /></svg>
            Booth {{ exhibitor.booth }}
          </p>
        </div>
      </header>

      <div class="scroll">
        <p v-if="exhibitor.description" class="desc">{{ exhibitor.description }}</p>

        <div class="actions">
          <a v-if="exhibitor.website" :href="exhibitor.website" target="_blank" rel="noopener" class="btn primary">
            <svg viewBox="0 0 24 24"><path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></svg>
            Visit website
          </a>
          <a
            v-for="[k, v] in socials"
            :key="k"
            :href="v"
            target="_blank"
            rel="noopener"
            class="ic"
            :title="k"
          >
            <svg viewBox="0 0 24 24"><path :d="socialIcons[k] || socialIcons.linkedin" /></svg>
          </a>
          <button
            class="ic save"
            :class="{ on: bookmarked }"
            type="button"
            :title="bookmarked ? 'Saved' : 'Save'"
            @click="bookmarks.toggle('exhibitor', exhibitor.id)"
          >
            <svg viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z" /></svg>
          </button>
        </div>

        <section v-if="exhibitor.products.length" class="sec">
          <h3>Products &amp; Services</h3>
          <div class="products">
            <article v-for="p in exhibitor.products" :key="p.id" class="product">
              <div v-if="p.image_url" class="pimg"><img :src="p.image_url" :alt="p.name"></div>
              <div class="pbody">
                <div class="prow">
                  <h4>{{ p.name }}</h4>
                  <span v-if="money(p.price)" class="price">{{ money(p.price) }}</span>
                </div>
                <p v-if="p.description" class="pdesc">{{ p.description }}</p>
              </div>
            </article>
          </div>
        </section>

        <section v-if="exhibitor.documents.length" class="sec">
          <h3>Documents</h3>
          <ul class="docs">
            <li v-for="d in exhibitor.documents" :key="d.id">
              <a :href="d.url || '#'" target="_blank" rel="noopener" class="doc">
                <svg viewBox="0 0 24 24"><path d="M14 3v5h5M14 3H6v18h12V8zM9 13h6M9 17h6" /></svg>
                <span>{{ d.title }}</span>
                <svg class="dl" viewBox="0 0 24 24"><path d="M12 3v12M7 12l5 5 5-5M5 21h14" /></svg>
              </a>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); display: flex; align-items: center; justify-content: center; padding: 16px; z-index: 60; }
.modal { position: relative; background: #fff; border-radius: 18px; width: 100%; max-width: 560px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.28); }

.x { position: absolute; top: 14px; right: 14px; z-index: 3; border: none; background: #f1f5f9; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.x svg { width: 16px; height: 16px; fill: none; stroke: #64748b; stroke-width: 2; stroke-linecap: round; }

.head { display: flex; gap: 16px; padding: 22px 22px 18px; border-bottom: 1px solid #eef0f3; }
.logo { width: 96px; height: 72px; flex: 0 0 auto; background: #f8fafc; border: 1px solid #eef0f3; border-radius: 12px; display: flex; align-items: center; justify-content: center; padding: 10px; }
.logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
.ini { font-size: 1.8rem; font-weight: 700; color: color-mix(in srgb, var(--brand-primary) 60%, #cbd5e1); }
.ident { min-width: 0; }
.tags { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
.tag { font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .3px; padding: 3px 8px; border-radius: 999px; }
.tag.exhibitor { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }
.tag.sponsor { background: #fef3c7; color: #b45309; }
.cat { font-size: .74rem; color: #64748b; }
.ident h2 { margin: 0; font-size: 1.3rem; font-weight: 800; color: #1e293b; }
.booth { display: flex; align-items: center; gap: 5px; margin: 6px 0 0; font-size: .82rem; color: #64748b; }
.booth svg { width: 15px; height: 15px; fill: none; stroke: var(--brand-primary); stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }

.scroll { padding: 20px 22px 24px; overflow-y: auto; }
.desc { margin: 0 0 16px; color: #475569; font-size: .92rem; line-height: 1.6; }

.actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.btn { display: inline-flex; align-items: center; gap: 7px; border: none; border-radius: 10px; padding: 11px 16px; font: inherit; font-size: .88rem; font-weight: 600; cursor: pointer; text-decoration: none; }
.btn svg { width: 16px; height: 16px; fill: none; stroke: currentColor; stroke-width: 1.9; stroke-linecap: round; stroke-linejoin: round; }
.btn.primary { background: var(--brand-primary); color: #fff; }
.ic { width: 40px; height: 40px; border-radius: 10px; background: #f1f5f9; color: #475569; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
.ic:hover { background: color-mix(in srgb, var(--brand-primary) 12%, #fff); color: var(--brand-primary); }
.ic svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.ic.save { border: none; cursor: pointer; font: inherit; }
.ic.save.on { background: var(--brand-primary); color: #fff; }
.ic.save.on svg { fill: currentColor; }

.sec { margin-top: 22px; }
.sec h3 { margin: 0 0 12px; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; color: #334155; }

.products { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 520px) { .products { grid-template-columns: 1fr; } }
.product { border: 1px solid #eef0f3; border-radius: 12px; overflow: hidden; }
.pimg { height: 110px; background: #f8fafc; }
.pimg img { width: 100%; height: 100%; object-fit: cover; }
.pbody { padding: 10px 12px 12px; }
.prow { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
.prow h4 { margin: 0; font-size: .9rem; font-weight: 700; color: #1e293b; }
.price { font-size: .82rem; font-weight: 700; color: var(--brand-primary); white-space: nowrap; }
.pdesc { margin: 4px 0 0; font-size: .8rem; color: #64748b; line-height: 1.45; }

.docs { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.doc { display: flex; align-items: center; gap: 10px; padding: 11px 14px; border: 1px solid #eef0f3; border-radius: 10px; text-decoration: none; color: #334155; font-size: .88rem; font-weight: 500; }
.doc:hover { background: #f7f8fa; }
.doc svg { width: 18px; height: 18px; fill: none; stroke: var(--brand-primary); stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
.doc span { flex: 1; }
.doc .dl { stroke: #94a3b8; width: 16px; height: 16px; }
</style>

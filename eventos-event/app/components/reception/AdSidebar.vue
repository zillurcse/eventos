<script setup lang="ts">
import type { ReceptionAd } from '~/stores/reception'

const props = defineProps<{ ads: ReceptionAd[] }>()

interface Card { key: string, adId: ReceptionAd['id'], src: string, href: string | null, alt: string }

const cards = computed<Card[]>(() =>
  props.ads.flatMap(ad =>
    (ad.images || [])
      .filter(img => (img.is_active ?? true) && (img.image_url || img.url))
      .map((img, i) => ({
        key: `${ad.id}-${i}`,
        adId: ad.id,
        src: (img.image_url || img.url) as string,
        href: (img.redirect_url as string) || null,
        alt: ad.title,
      })),
  ),
)

// One impression per ad on first render, a click when followed — feeds Insights.
const seen = new Set<ReceptionAd['id']>()
function recordImpressions() {
  for (const ad of props.ads) {
    if (seen.has(ad.id)) continue
    seen.add(ad.id)
    trackAd(ad.id, 'impression')
  }
}
onMounted(recordImpressions)
watch(() => props.ads.map(a => a.id).join(','), recordImpressions)
</script>

<template>
  <div v-if="cards.length" class="side-ads">
    <component :is="c.href ? 'a' : 'div'" v-for="c in cards" :key="c.key" class="ad" :href="c.href || undefined"
      :target="c.href ? '_blank' : undefined" rel="noopener" @click="c.href && trackAd(c.adId, 'click')">
      <img :src="c.src" :alt="c.alt" />
    </component>
  </div>
</template>

<style scoped>
.side-ads {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.ad {
  display: block;
  border-radius: 12px;
  overflow: hidden;
  background: var(--brand-content-bg, #fff);
}

.ad img {
  display: block;
  width: 100%;
  height: auto;
  object-fit: cover;
}

:global(html[data-theme="minimal"]) .side-ads { gap: 10px; }
:global(html[data-theme="minimal"]) .ad {
  border-radius: var(--theme-radius, 8px);
  border: 1px solid var(--line, #e2e8f0);
}

:global(html[data-theme="modern"]) .side-ads {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 20px;
}
:global(html[data-theme="modern"]) .ad {
  border-radius: var(--theme-radius, 18px);
  box-shadow: var(--theme-shadow, 0 8px 28px rgba(15, 23, 42, 0.06));
  overflow: hidden;
}
</style>

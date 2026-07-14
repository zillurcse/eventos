<script setup lang="ts">
import type { ReceptionAd } from '~/stores/reception'

const props = defineProps<{ ads: ReceptionAd[] }>()

interface Card { key: string, src: string, href: string | null, alt: string }

const cards = computed<Card[]>(() =>
  props.ads.flatMap(ad =>
    (ad.images || [])
      .filter(img => (img.is_active ?? true) && (img.image_url || img.url))
      .map((img, i) => ({
        key: `${ad.id}-${i}`,
        src: (img.image_url || img.url) as string,
        href: (img.redirect_url as string) || null,
        alt: ad.title,
      })),
  ),
)
</script>

<template>
  <div v-if="cards.length" class="side-ads">
    <component :is="c.href ? 'a' : 'div'" v-for="c in cards" :key="c.key" class="ad" :href="c.href || undefined"
      :target="c.href ? '_blank' : undefined" rel="noopener">
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
  background: #fff;
}

.ad img {
  display: block;
  width: 100%;
  height: auto;
  object-fit: cover;
}
</style>
